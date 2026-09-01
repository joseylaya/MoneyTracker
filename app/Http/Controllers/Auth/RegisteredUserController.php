<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TrackerInvitation;
use App\Models\TrackerMember;
use App\Models\ActivityLog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required','string','lowercase','min:3','max:40','regex:/^[a-z0-9_]+$/','unique:users,username'],
            'email' => 'nullable|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create(['name' => $request->username, 'username' => $request->username, 'email' => $request->email, 'password' => Hash::make($request->password)]);
            $invitations = $user->email ? TrackerInvitation::whereRaw('lower(email) = ?', [strtolower($user->email)])->where('status', 'pending')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get() : collect();
            foreach ($invitations as $invitation) {
                TrackerMember::firstOrCreate(['tracker_id' => $invitation->tracker_id, 'user_id' => $user->id], ['role' => $invitation->role, 'status' => 'active', 'joined_at' => now(), 'created_by' => $invitation->invited_by]);
                $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);
                ActivityLog::create(['tracker_id' => $invitation->tracker_id, 'actor_user_id' => $user->id, 'action' => 'member.joined', 'subject_type' => 'member', 'subject_id' => (string) $user->id, 'metadata' => ['name' => $user->name, 'role' => $invitation->role], 'created_at' => now()]);
            }
            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $intended = $request->session()->pull('url.intended');
        if ($intended && str_starts_with((string) parse_url($intended, PHP_URL_PATH), '/join/')) {
            return redirect()->to($intended);
        }

        return redirect()->route('trackers.index');
    }
}
