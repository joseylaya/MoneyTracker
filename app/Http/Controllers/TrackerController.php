<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Actions\CreateExpense;
use App\Actions\UpdateExpense;
use App\Models\ExpenseSplit;
use App\Models\Settlement;
use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\TrackerSettlementRequest;
use App\Models\TrackerMessage;
use App\Models\User;
use App\Models\TrackerInvitation;
use App\Models\ItineraryItem;
use App\Events\TrackerMessageCreated;
use App\Services\TrackerFinance;
use App\Services\TrackerCache;
use App\Services\TrackerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TrackerController extends Controller
{
    public function index(Request $request, TrackerFinance $finance): Response
    {
        $trackers = Tracker::where('status', 'active')->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'))
            ->withCount(['members' => fn ($q) => $q->where('status', 'active')])->latest()->get()
            ->map(function (Tracker $tracker) use ($finance, $request) {
                $tracker->current_user_balance_minor = $finance->memberBalances($tracker)[$request->user()->id] ?? 0;
                $tracker->unread_notifications_count = $tracker->notifications()->where('user_id', $request->user()->id)->whereNull('dismissed_at')->whereNull('read_at')->count();
                return $tracker;
            });
        $archivedTrackers = Tracker::where('status', 'archived')->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'))
            ->withCount(['members' => fn ($q) => $q->where('status', 'active')])->latest('updated_at')->get()
            ->each(fn (Tracker $tracker) => $tracker->can_manage_lifecycle = $tracker->owner_user_id === $request->user()->id);
        return Inertia::render('Trackers/Index', ['trackers' => $trackers, 'archivedTrackers' => $archivedTrackers]);
    }

    public function create(): Response { return Inertia::render('Trackers/Create'); }

    public function archive(Request $request, Tracker $tracker, TrackerCache $cache, TrackerFinance $finance): RedirectResponse
    {
        $this->authorize('manageLifecycle', $tracker);
        $tracker->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
        $cache->forget($tracker); $finance->forget($tracker);
        return to_route('trackers.index')->with('success', $tracker->name.' was archived. Its history is preserved.');
    }

    public function restore(Request $request, Tracker $tracker, TrackerCache $cache, TrackerFinance $finance): RedirectResponse
    {
        $this->authorize('manageLifecycle', $tracker);
        $tracker->update(['status' => 'active', 'updated_by' => $request->user()->id]);
        $cache->forget($tracker); $finance->forget($tracker);
        return back()->with('success', $tracker->name.' is active again.');
    }

    public function destroy(Request $request, Tracker $tracker, TrackerCache $cache, TrackerFinance $finance): RedirectResponse
    {
        $this->authorize('manageLifecycle', $tracker);
        $tracker->update(['deleted_by' => $request->user()->id]);
        $tracker->delete(); $cache->forget($tracker); $finance->forget($tracker);
        return to_route('trackers.index')->with('success', $tracker->name.' was deleted.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'], 'currency_code' => ['required', 'in:PHP,USD,EUR']]);
        $user = $request->user();
        $tracker = DB::transaction(function () use ($data, $user) {
            $tracker = Tracker::create([...$data, 'currency_exponent' => 2, 'owner_user_id' => $user->id, 'created_by' => $user->id]);
            TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now(), 'created_by' => $user->id]);
            $this->activity($tracker, $user->id, 'tracker.created', 'tracker', $tracker->id, ['name' => $tracker->name]);
            return $tracker;
        });
        return to_route('trackers.show', $tracker)->with('success', 'Tracker created.');
    }

    public function show(Request $request, Tracker $tracker, TrackerFinance $finance, TrackerCache $cache): Response
    {
        $this->authorize('view', $tracker);
        $membership = $this->membership($tracker, $request->user()->id);
        $members = collect($cache->activeMembers($tracker))->map(fn ($member) => collect($member)->only(['id', 'name', 'email', 'role'])->all());
        $balances = $finance->memberBalances($tracker);
        $memberData = $members->map(fn ($member) => [...$member, 'balance_minor' => $balances[$member['id']] ?? 0]);
        $expenses = $tracker->expenses()->with(['payer:id,name', 'splits.user:id,name'])->withCount('comments')->orderByDesc('expense_date')->orderByDesc('created_at')->get();
        $unreadMessagesCount = $tracker->messages()
            ->where('user_id', '!=', $request->user()->id)
            ->when($membership->last_read_chat_at, fn ($query, $lastReadAt) => $query->where('created_at', '>', $lastReadAt))
            ->count();
        $settlementRequests = $tracker->settlementRequests()
            ->where(fn ($query) => $query->where('from_user_id', $request->user()->id)->orWhere('to_user_id', $request->user()->id))
            ->with(['fromUser:id,name', 'toUser:id,name'])->latest()->get();
        $approvedRequests = $settlementRequests->where('status', 'approved')->whereNotNull('approved_settlement_id')->keyBy('approved_settlement_id');
        $settlementHistory = Settlement::withTrashed()
            ->where('tracker_id', $tracker->id)
            ->where(fn ($query) => $query->where('from_user_id', $request->user()->id)->orWhere('to_user_id', $request->user()->id))
            ->with(['sender:id,name', 'recipient:id,name'])->latest()->get()
            ->map(function (Settlement $settlement) use ($request, $approvedRequests) {
                $outgoing = $settlement->from_user_id === $request->user()->id;
                return [
                    'id' => $settlement->id, 'type' => 'settlement', 'direction' => $outgoing ? 'out' : 'in',
                    'status' => $settlement->trashed() ? 'reversed' : ($approvedRequests->has($settlement->id) ? 'approved' : 'completed'),
                    'title' => $outgoing ? 'Payment to '.$settlement->recipient->name : 'Payment from '.$settlement->sender->name,
                    'amount_minor' => (int) $settlement->amount_minor,
                    'date' => $settlement->settlement_date?->format('Y-m-d'), 'created_at' => $settlement->created_at,
                ];
            });
        $requestHistory = $settlementRequests
            ->filter(fn (TrackerSettlementRequest $settlementRequest) => $settlementRequest->status !== 'approved' || ! $settlementRequest->approved_settlement_id)
            ->map(fn (TrackerSettlementRequest $settlementRequest) => [
                'id' => $settlementRequest->id, 'type' => 'settlement',
                'direction' => $settlementRequest->from_user_id === $request->user()->id ? 'out' : 'in',
                'status' => $settlementRequest->status,
                'title' => $settlementRequest->from_user_id === $request->user()->id
                    ? 'Payment to '.$settlementRequest->toUser->name
                    : 'Payment from '.$settlementRequest->fromUser->name,
                'amount_minor' => (int) $settlementRequest->amount_minor,
                'date' => $settlementRequest->settlement_date?->format('Y-m-d'), 'created_at' => $settlementRequest->created_at,
            ]);
        $personalTransactions = $expenses
            ->where('created_by', $request->user()->id)
            ->map(fn (Expense $expense) => [
                'id' => $expense->id, 'type' => 'expense', 'direction' => 'expense', 'status' => 'recorded',
                'title' => $expense->description, 'amount_minor' => (int) $expense->amount_minor,
                'date' => $expense->expense_date?->format('Y-m-d'), 'created_at' => $expense->created_at,
            ])
            ->concat($settlementHistory)
            ->concat($requestHistory)
            ->sortByDesc(fn (array $item) => $item['date'].' '.$item['created_at'])
            ->values();
        $tasks = $tracker->tasks()->with(['assignee:id,name', 'completedBy:id,name'])
            ->orderByRaw('completed_at is not null')->orderBy('created_at')->get();
        $plannedExpenses = $tracker->plannedExpenses()->with('assignee:id,name')
            ->orderBy('expected_date')->orderBy('created_at')->get();
        return Inertia::render('Trackers/Show', [
            'tracker' => $tracker,
            'membership' => ['role' => $membership->role, 'can_manage_members' => $request->user()->can('manageMembers', $tracker), 'can_manage_finances' => $request->user()->can('update', $tracker), 'can_settle' => $request->user()->can('settle', $tracker), 'can_manage_lifecycle' => $request->user()->can('manageLifecycle', $tracker)],
            'members' => $memberData,
            'expenses' => $expenses,
            'myExpenseTotalMinor' => $expenses->where('paid_by_user_id', $request->user()->id)->sum('amount_minor'),
            'debts' => $finance->spenderObligations($tracker),
            'currentUserId' => $request->user()->id,
            'unreadMessagesCount' => $unreadMessagesCount,
            'unreadNotificationsCount' => $tracker->notifications()->where('user_id', $request->user()->id)->whereNull('dismissed_at')->whereNull('read_at')->count(),
            'personalTransactions' => $personalTransactions,
            'tasks' => $tasks,
            'plannedExpenses' => $plannedExpenses,
        ]);
    }

    public function shareLink(Request $request, Tracker $tracker)
    {
        $this->authorize('manageMembers', $tracker);

        if (! $tracker->share_token) {
            $tracker->forceFill(['share_token' => (string) Str::uuid()])->save();
        }

        return response()->json([
            'url' => route('trackers.share.join', $tracker->share_token),
        ]);
    }

    public function joinShared(Request $request, string $token, TrackerCache $cache, TrackerFinance $finance): RedirectResponse
    {
        // Some mobile share sheets append the invitation text to the URL and
        // make the whole message clickable. Accept the UUID prefix while never
        // passing malformed UUID input to PostgreSQL.
        preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i', $token, $matches);
        abort_unless(isset($matches[0]), 404);
        $token = strtolower($matches[0]);

        $tracker = Tracker::where('share_token', $token)->where('status', 'active')->firstOrFail();

        if (! $request->user()) {
            $request->session()->put('url.intended', route('trackers.share.join', $token));
            return redirect()->route('register', status: 303)->with('status', 'Create an account to join '.$tracker->name.'.');
        }

        $membership = $tracker->members()->where('user_id', $request->user()->id)->first();
        if (! $membership) {
            TrackerMember::create([
                'tracker_id' => $tracker->id,
                'user_id' => $request->user()->id,
                'role' => 'editor',
                'status' => 'active',
                'joined_at' => now(),
                'created_by' => $tracker->owner_user_id,
            ]);
            $this->activity($tracker, $request->user()->id, 'member.joined', 'member', (string) $request->user()->id, ['name' => $request->user()->name, 'role' => 'editor', 'source' => 'share_link']);
        } elseif ($membership->status !== 'active') {
            $membership->update(['status' => 'active', 'role' => 'editor', 'joined_at' => now(), 'removed_at' => null, 'updated_by' => $request->user()->id]);
        }

        $cache->forget($tracker);
        $finance->forget($tracker);

        return redirect()->route('trackers.show', $tracker, status: 303)->with('success', $membership ? 'Tracker opened.' : 'Tracker added to your account.');
    }

    public function members(Request $request, Tracker $tracker, TrackerCache $cache): Response
    {
        $this->authorize('view', $tracker);
        return Inertia::render('Trackers/Members', [
            'tracker' => $tracker,
            'members' => $cache->activeMembers($tracker),
            'invitations' => $tracker->invitations()->where('status', 'pending')->latest()->get(['id', 'email', 'role', 'created_at']),
            'canManage' => $request->user()->can('manageMembers', $tracker),
            'canCreateDummy' => $request->user()->can('update', $tracker),
        ]);
    }

    public function memberSuggestions(Request $request, Tracker $tracker)
    {
        $this->authorize('manageMembers', $tracker);
        $query = trim((string) $request->query('query', ''));
        if (mb_strlen($query) < 2) return response()->json(['suggestions' => []]);

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($query)).'%';
        $suggestions = User::query()
            ->where('is_placeholder', false)
            ->where('id', '!=', $request->user()->id)
            ->whereDoesntHave('trackerMemberships', fn ($members) => $members->where('tracker_id', $tracker->id)->where('status', 'active'))
            ->where(fn ($users) => $users->whereRaw('lower(email) like ?', [$like])->orWhereRaw('lower(name) like ?', [$like]))
            ->orderBy('name')->limit(6)->get(['id', 'name', 'email']);

        return response()->json(['suggestions' => $suggestions]);
    }

    public function expenseCreate(Request $request, Tracker $tracker, TrackerCache $cache): Response
    {
        $this->authorize('update', $tracker);
        $initialItineraryItemId = $request->query('itinerary_item');
        $this->ensureExpenseItinerary($tracker, $initialItineraryItemId);
        return Inertia::render('Expenses/Create', [
            'tracker' => $tracker,
            'members' => collect($cache->activeMembers($tracker))->map(fn ($member) => collect($member)->only(['id', 'name', 'role'])->all())->values(),
            'currentUserId' => $request->user()->id,
            'itineraryOptions' => $this->itineraryOptions($tracker),
            'initialItineraryItemId' => $initialItineraryItemId,
        ]);
    }

    public function expenseShow(Request $request, Tracker $tracker, Expense $expense): Response
    {
        $this->authorize('view', $tracker);
        abort_unless($expense->tracker_id === $tracker->id, 404);
        $expense->load(['payer:id,name,email', 'splits.user:id,name,email']);
        $comments = $expense->comments()->with('author:id,name')->latest('created_at')->limit(11)->get();
        $hasMoreComments = $comments->count() > 10;
        $expense->setRelation('comments', $comments->take(10)->sortBy('created_at')->values());
        return Inertia::render('Expenses/Show', ['tracker' => $tracker, 'expense' => $expense, 'hasMoreComments' => $hasMoreComments, 'canManage' => $request->user()->can('update', $tracker), 'canComment' => $request->user()->can('comment', $tracker), 'currentUserId' => $request->user()->id]);
    }

    public function expenseEdit(Request $request, Tracker $tracker, Expense $expense, TrackerCache $cache): Response
    {
        $this->authorize('update', $tracker);
        abort_unless($expense->tracker_id === $tracker->id, 404);
        $expense->load(['splits.user:id,name']);
        return Inertia::render('Expenses/Edit', ['tracker' => $tracker, 'expense' => $expense, 'members' => collect($cache->activeMembers($tracker))->map(fn ($member) => collect($member)->only(['id', 'name'])->all())->values(), 'itineraryOptions' => $this->itineraryOptions($tracker)]);
    }

    public function updateExpense(Request $request, Tracker $tracker, Expense $expense, UpdateExpense $updater, TrackerNotifier $notifier): RedirectResponse
    {
        $this->authorize('update', $tracker);
        abort_unless($expense->tracker_id === $tracker->id, 404);
        $data = $this->expenseData($request);
        $this->ensureExpenseItinerary($tracker, $data['itinerary_item_id'] ?? null);
        $memberIds = $tracker->members()->where('status', 'active')->pluck('user_id')->all();
        abort_unless(in_array((int) $data['paid_by_user_id'], $memberIds, true) && empty(array_diff($data['participants'], $memberIds)), 422);
        $updated = $updater->handle($expense, $request->user(), $this->prepareExpenseData($data));
        $notifier->members($tracker, $request->user(), 'expense.updated', 'Expense updated in '.$tracker->name, $request->user()->name.' updated '.$updated->description.'. Balances were recalculated.', route('trackers.expenses.show', [$tracker, $updated]), ['expense_id' => $updated->id]);
        return to_route('trackers.expenses.show', [$tracker, $updated])->with('success', 'Expense updated. All balances and settlement suggestions were recalculated.');
    }

    public function destroyExpense(Request $request, Tracker $tracker, Expense $expense, TrackerFinance $finance, TrackerNotifier $notifier): RedirectResponse
    {
        $this->authorize('update', $tracker);
        abort_unless($expense->tracker_id === $tracker->id, 404);

        DB::transaction(function () use ($request, $tracker, $expense) {
            $expense->update(['deleted_by' => $request->user()->id]);
            $expense->delete();
            $this->activity($tracker, $request->user()->id, 'expense.deleted', 'expense', $expense->id, [
                'description' => $expense->description,
                'amount_minor' => $expense->amount_minor,
            ]);
        });

        $finance->forget($tracker);
        $notifier->members($tracker, $request->user(), 'expense.deleted', 'Expense deleted in '.$tracker->name, $request->user()->name.' deleted '.$expense->description.'. Balances were recalculated.', route('trackers.show', $tracker), ['expense_id' => $expense->id]);

        return to_route('trackers.show', $tracker)->with('success', $expense->description.' was deleted and balances were recalculated.');
    }

    public function addMember(Request $request, Tracker $tracker, TrackerCache $cache, TrackerFinance $finance, TrackerNotifier $notifier): RedirectResponse
    {
        $this->authorize('manageMembers', $tracker);
        $data = $request->validate(['email' => ['required', 'email'], 'role' => ['required', 'in:editor,commenter,viewer']]);
        $user = User::whereRaw('lower(email) = ?', [strtolower($data['email'])])->first();
        if (! $user) {
            TrackerInvitation::updateOrCreate(['tracker_id' => $tracker->id, 'email' => strtolower($data['email']), 'status' => 'pending'], ['role' => $data['role'], 'invited_by' => $request->user()->id, 'expires_at' => now()->addDays(14)]);
            $cache->forget($tracker);
            $this->activity($tracker, $request->user()->id, 'invitation.sent', 'invitation', '', ['email' => strtolower($data['email']), 'role' => $data['role']]);
            return back()->with('success', 'Invitation is pending until this email registers.');
        }
        if ($this->membership($tracker, $user->id)) return back()->withErrors(['email' => 'This user is already a tracker member.']);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => $data['role'], 'status' => 'active', 'joined_at' => now(), 'created_by' => $request->user()->id]);
        $cache->forget($tracker);
        $finance->forget($tracker);
        $notifier->user($user->id, $tracker, $request->user()->id, 'member.joined', 'You joined '.$tracker->name, $request->user()->name.' added you as a '.$data['role'].'.', route('trackers.show', $tracker));
        $this->activity($tracker, $request->user()->id, 'member.joined', 'member', (string) $user->id, ['name' => $user->name, 'role' => $data['role']]);
        return back()->with('success', "$user->name was added to the tracker.");
    }

    public function addDummyMember(Request $request, Tracker $tracker, TrackerCache $cache, TrackerFinance $finance): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $actor = $request->user();

        $dummy = DB::transaction(function () use ($data, $tracker, $actor) {
            $dummy = User::create([
                'name' => trim($data['name']),
                'username' => 'dummy_'.Str::lower(Str::random(20)),
                'email' => null,
                'password' => Str::random(64),
                'is_placeholder' => true,
                'placeholder_tracker_id' => $tracker->id,
            ]);
            TrackerMember::create([
                'tracker_id' => $tracker->id,
                'user_id' => $dummy->id,
                'role' => 'viewer',
                'status' => 'active',
                'joined_at' => now(),
                'created_by' => $actor->id,
            ]);
            $this->activity($tracker, $actor->id, 'member.placeholder_created', 'user', (string) $dummy->id, ['name' => $dummy->name]);

            return $dummy;
        });

        $cache->forget($tracker);
        $finance->forget($tracker);

        return back()->with('success', $dummy->name.' was added as a dummy member.');
    }

    public function mergeDummyMember(Request $request, Tracker $tracker, TrackerMember $member, TrackerCache $cache, TrackerFinance $finance, TrackerNotifier $notifier): RedirectResponse
    {
        $this->authorize('manageMembers', $tracker);
        $data = $request->validate(['target_user_id' => ['required', 'integer', 'exists:users,id']]);
        abort_unless($member->tracker_id === $tracker->id && $member->status === 'active', 422);

        $dummy = $member->user()->firstOrFail();
        abort_unless($dummy->is_placeholder && $dummy->placeholder_tracker_id === $tracker->id, 422);
        $targetMembership = $tracker->members()->where('user_id', $data['target_user_id'])->where('status', 'active')->firstOrFail();
        $target = $targetMembership->user()->firstOrFail();
        abort_if($target->is_placeholder || $target->id === $dummy->id, 422);

        DB::transaction(function () use ($request, $tracker, $member, $dummy, $target) {
            $expenseIds = Expense::withTrashed()->where('tracker_id', $tracker->id)->pluck('id');
            Expense::withTrashed()->where('tracker_id', $tracker->id)->where('paid_by_user_id', $dummy->id)->update(['paid_by_user_id' => $target->id]);

            ExpenseSplit::whereIn('expense_id', $expenseIds)->where('user_id', $dummy->id)->get()->each(function (ExpenseSplit $dummySplit) use ($target) {
                $targetSplit = ExpenseSplit::where('expense_id', $dummySplit->expense_id)->where('user_id', $target->id)->first();
                if ($targetSplit) {
                    $targetSplit->increment('amount_minor', $dummySplit->amount_minor);
                    if ($targetSplit->quantity !== null || $dummySplit->quantity !== null) {
                        $targetSplit->update(['quantity' => (int) $targetSplit->quantity + (int) $dummySplit->quantity]);
                    }
                    $dummySplit->delete();
                } else {
                    $dummySplit->update(['user_id' => $target->id]);
                }
            });

            Settlement::withTrashed()->where('tracker_id', $tracker->id)->where('from_user_id', $dummy->id)->get()->each(function (Settlement $settlement) use ($target) {
                $settlement->from_user_id = $target->id;
                $settlement->save();
                if ($settlement->to_user_id === $target->id && ! $settlement->trashed()) $settlement->delete();
            });
            Settlement::withTrashed()->where('tracker_id', $tracker->id)->where('to_user_id', $dummy->id)->get()->each(function (Settlement $settlement) use ($target) {
                $settlement->to_user_id = $target->id;
                $settlement->save();
                if ($settlement->from_user_id === $target->id && ! $settlement->trashed()) $settlement->delete();
            });

            TrackerSettlementRequest::where('tracker_id', $tracker->id)
                ->where(fn ($query) => $query->where('from_user_id', $dummy->id)->orWhere('to_user_id', $dummy->id))
                ->get()
                ->each(function (TrackerSettlementRequest $settlementRequest) use ($request, $dummy, $target) {
                    if ($settlementRequest->from_user_id === $dummy->id) $settlementRequest->from_user_id = $target->id;
                    if ($settlementRequest->to_user_id === $dummy->id) $settlementRequest->to_user_id = $target->id;
                    if ($settlementRequest->from_user_id === $settlementRequest->to_user_id) {
                        $settlementRequest->status = 'declined';
                        $settlementRequest->responded_by = $request->user()->id;
                        $settlementRequest->responded_at = now();
                    }
                    $settlementRequest->save();
                });

            $member->update([
                'status' => 'merged',
                'removed_at' => now(),
                'removed_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'merged_into_user_id' => $target->id,
                'merged_at' => now(),
            ]);
            $this->activity($tracker, $request->user()->id, 'member.placeholder_merged', 'member', $member->id, [
                'placeholder_user_id' => $dummy->id,
                'placeholder_name' => $dummy->name,
                'target_user_id' => $target->id,
                'target_name' => $target->name,
            ]);
        });

        $cache->forget($tracker);
        $finance->forget($tracker);
        $notifier->members($tracker, $request->user(), 'member.placeholder_merged', 'Dummy member merged in '.$tracker->name, $dummy->name.' was merged into '.$target->name.'.', route('trackers.show', $tracker));

        return back()->with('success', $dummy->name.' was merged into '.$target->name.'. Their expenses and balances are now combined.');
    }

    public function changeMemberRole(Request $request, Tracker $tracker, TrackerMember $member, TrackerCache $cache, TrackerNotifier $notifier): RedirectResponse
    {
        $this->authorize('manageMembers', $tracker);
        abort_unless($member->tracker_id === $tracker->id && $member->role !== 'owner', 422);
        $data = $request->validate(['role' => ['required', 'in:editor,commenter,viewer']]);
        $oldRole = $member->role; $member->update(['role' => $data['role'], 'updated_by' => $request->user()->id]);
        $cache->forget($tracker);
        $notifier->user($member->user_id, $tracker, $request->user()->id, 'member.role_changed', 'Your role changed in '.$tracker->name, $request->user()->name.' changed your role to '.$data['role'].'.', route('trackers.members.index', $tracker));
        $this->activity($tracker, $request->user()->id, 'member.role_changed', 'member', $member->id, ['old_role' => $oldRole, 'new_role' => $data['role']]);
        return back()->with('success', 'Member role updated.');
    }

    public function removeMember(Request $request, Tracker $tracker, TrackerMember $member, TrackerCache $cache, TrackerFinance $finance, TrackerNotifier $notifier): RedirectResponse
    {
        $this->authorize('manageMembers', $tracker);
        abort_unless($member->tracker_id === $tracker->id && $member->role !== 'owner' && $member->status === 'active', 422);

        $balance = $finance->memberBalances($tracker)[$member->user_id] ?? 0;
        if ($balance !== 0) {
            return back()->withErrors(['member' => 'This member still has an outstanding balance. Settle it before removing them.']);
        }

        $removedUser = $member->user()->firstOrFail();
        $member->update([
            'status' => 'removed',
            'removed_at' => now(),
            'removed_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $cache->forget($tracker);
        $finance->forget($tracker);
        $this->activity($tracker, $request->user()->id, 'member.removed', 'member', $member->id, ['name' => $removedUser->name, 'user_id' => $removedUser->id]);
        if (! $removedUser->is_placeholder) {
            $notifier->user($removedUser->id, $tracker, $request->user()->id, 'member.removed', 'Removed from '.$tracker->name, $request->user()->name.' removed you from '.$tracker->name.'.', route('trackers.index'));
        }

        return back()->with('success', $removedUser->name.' was removed from the tracker.');
    }

    public function storeExpense(Request $request, Tracker $tracker, TrackerNotifier $notifier): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $data = $this->expenseData($request);
        $this->ensureExpenseItinerary($tracker, $data['itinerary_item_id'] ?? null);
        $memberIds = $tracker->members()->where('status', 'active')->pluck('user_id')->all();
        abort_unless(in_array((int) $data['paid_by_user_id'], $memberIds, true) && empty(array_diff($data['participants'], $memberIds)), 422);
        $expense = app(CreateExpense::class)->handle($tracker, $request->user(), $this->prepareExpenseData($data));
        $notifier->members($tracker, $request->user(), 'expense.created', 'New expense in '.$tracker->name, $request->user()->name.' added '.$expense->description.'.', route('trackers.expenses.show', [$tracker, $expense]), ['expense_id' => $expense->id]);
        return to_route('trackers.show', $tracker)->with('success', "{$expense->description} was added.");
    }

    public function settlementCreate(Request $request, Tracker $tracker, TrackerFinance $finance, TrackerCache $cache): Response
    {
        $this->authorize('settle', $tracker);
        $members = collect($cache->activeMembers($tracker))->map(fn ($member) => collect($member)->only(['id', 'name'])->all())->values();
        $debts = collect($finance->spenderObligations($tracker))->where('from_user_id', $request->user()->id)->values();
        return Inertia::render('Settlements/Create', ['tracker' => $tracker, 'members' => $members, 'debts' => $debts, 'currentUserId' => $request->user()->id]);
    }

    public function storeSettlement(Request $request, Tracker $tracker, TrackerFinance $finance, TrackerNotifier $notifier): RedirectResponse
    {
        $this->authorize('settle', $tracker);
        $data = $request->validate(['from_user_id' => ['required', 'integer'], 'to_user_id' => ['required', 'integer', 'different:from_user_id'], 'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'], 'settlement_date' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:2000']]);
        $amount = $this->minor($data['amount']);
        if ($amount <= 0) return back()->withErrors(['amount' => 'The settlement amount must be greater than zero.']);
        $activeIds = $tracker->members()->where('status', 'active')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        abort_unless(in_array((int) $data['from_user_id'], $activeIds, true) && in_array((int) $data['to_user_id'], $activeIds, true), 422);
        abort_unless($request->user()->id === (int) $data['from_user_id'], 403);
        $debt = collect($finance->spenderObligations($tracker))->first(fn ($debt) => $debt['from_user_id'] === (int) $data['from_user_id'] && $debt['to_user_id'] === (int) $data['to_user_id']);
        if (! $debt || $amount > $debt['amount_minor']) return back()->withErrors(['amount' => 'The settlement cannot be greater than this obligation.']);
        $message = DB::transaction(function () use ($tracker, $request, $data, $amount) {
            $settlementRequest = TrackerSettlementRequest::create(['tracker_id' => $tracker->id, 'from_user_id' => $request->user()->id, 'to_user_id' => $data['to_user_id'], 'amount_minor' => $amount, 'settlement_date' => $data['settlement_date'], 'note' => $data['note'] ?? null]);
            return TrackerMessage::create(['tracker_id' => $tracker->id, 'user_id' => $request->user()->id, 'body' => '', 'type' => 'settlement_request', 'settlement_request_id' => $settlementRequest->id]);
        })->load(['author:id,name', 'attachments', 'settlementRequest.fromUser:id,name', 'settlementRequest.toUser:id,name']);
        TrackerMessageCreated::dispatch($message);
        $notifier->user($message->settlementRequest->to_user_id, $tracker, $request->user()->id, 'settlement.requested', 'Settlement approval requested', $request->user()->name.' requested approval for a settlement in '.$tracker->name, route('trackers.conversation.index', $tracker));
        return to_route('trackers.show', $tracker)->with('success', 'Settlement sent for approval. Balances will change after the recipient accepts it.');
    }

    private function membership(Tracker $tracker, int $userId): ?TrackerMember { return $tracker->members()->where('user_id', $userId)->where('status', 'active')->first(); }
    private function expenseData(Request $request): array
    {
        $request->merge([
            'expense_type' => $request->input('expense_type', 'split'),
            'split_method' => $request->input('split_method', 'equal'),
            'participants' => $request->input('participants', []),
        ]);

        return $request->validate([
            'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paid_by_user_id' => ['required', 'integer'], 'expense_date' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:2000'],
            'itinerary_item_id' => ['nullable', 'uuid'],
            'expense_type' => ['required', 'in:split,sponsored'], 'split_method' => ['required', 'in:equal,quantity'],
            'participants' => ['array', 'required_if:expense_type,split'], 'participants.*' => ['integer'],
            'unit_price' => ['nullable', 'required_if:split_method,quantity', 'regex:/^\d+(\.\d{1,2})?$/'],
            'quantities' => ['nullable', 'required_if:split_method,quantity', 'array'], 'quantities.*' => ['integer', 'min:0'],
        ]);
    }
    private function prepareExpenseData(array $data): array
    {
        return [...$data, 'note' => $data['note'] ?? null, 'amount_minor' => $this->minor($data['amount']),
            'unit_price_minor' => isset($data['unit_price']) ? $this->minor($data['unit_price']) : null,
            'participants' => array_values(array_unique(array_map('intval', $data['participants'] ?? [])))];
    }
    private function minor(string $amount): int { [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, ''); return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0'); }
    private function ensureExpenseItinerary(Tracker $tracker, ?string $itemId): void
    {
        if ($itemId) abort_unless(ItineraryItem::whereKey($itemId)->whereHas('day', fn ($query) => $query->where('tracker_id', $tracker->id))->exists(), 422);
    }
    private function itineraryOptions(Tracker $tracker): array
    {
        return $tracker->itineraryDays()->with('items:id,itinerary_day_id,title,start_time,sort_order')->get()->flatMap(fn ($day) => $day->items->map(fn ($item) => ['id' => $item->id, 'label' => $day->date->format('M j').' · '.$item->title]))->values()->all();
    }
    private function activity(Tracker $tracker, int $actorId, string $action, string $type, string $id, array $metadata): void { ActivityLog::create(['tracker_id' => $tracker->id, 'actor_user_id' => $actorId, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'metadata' => $metadata, 'created_at' => now()]); }
}
