<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $disk = Storage::disk(config('filesystems.profile_photos_disk'));
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ]);

        $source = file_get_contents($request->file('photo')->getRealPath());
        $image = $source === false ? false : imagecreatefromstring($source);
        abort_unless($image !== false, 422, 'The selected image could not be processed.');

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 512 / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $encoded = null;
        foreach ([78, 68, 58] as $quality) {
            ob_start();
            imagewebp($resized, null, $quality);
            $encoded = ob_get_clean();
            if (strlen($encoded) <= 250 * 1024) {
                break;
            }
        }
        imagedestroy($image);
        imagedestroy($resized);
        abort_unless(is_string($encoded) && $encoded !== '', 422, 'The selected image could not be converted to WebP.');

        $path = 'profile-photos/'.$request->user()->id.'-'.Str::uuid().'.webp';
        abort_unless($disk->put($path, $encoded), 500, 'The profile photo could not be saved.');
        $oldPath = $request->user()->avatar_path;
        $request->user()->forceFill(['avatar_path' => $path])->save();
        if ($oldPath) {
            $disk->delete($oldPath);
        }

        return Redirect::route('profile.edit')->with('success', 'Profile photo updated.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $disk = Storage::disk(config('filesystems.profile_photos_disk'));
        $oldPath = $request->user()->avatar_path;
        $request->user()->forceFill(['avatar_path' => null])->save();
        if ($oldPath) {
            $disk->delete($oldPath);
        }

        return Redirect::route('profile.edit')->with('success', 'Profile photo removed.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk(config('filesystems.profile_photos_disk'))->delete($user->avatar_path);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
