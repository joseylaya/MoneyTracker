<?php

namespace App\Http\Controllers;

use App\Events\TrackerMessageCreated;
use App\Events\TrackerMessageReactionUpdated;
use App\Events\TrackerSettlementRequestUpdated;
use App\Services\TrackerNotifier;
use App\Models\Settlement;
use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\TrackerMessage;
use App\Models\TrackerMessageAttachment;
use App\Models\TrackerSettlementRequest;
use App\Models\TrackerNotification;
use App\Services\TrackerFinance;
use App\Services\TrackerCache;
use App\Services\ConversationPresence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TrackerConversationController extends Controller
{
    public function index(Request $request, Tracker $tracker, TrackerFinance $finance, TrackerCache $cache): Response
    {
        $this->authorize('view', $tracker);
        $membership = TrackerMember::where('tracker_id', $tracker->id)->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        $messages = $this->messageQuery($tracker)->latest('created_at')->limit(11)->get();
        $hasMoreMessages = $messages->count() > 10;
        $messages = $messages->take(10)->sortBy('created_at')->values();
        $membership->update(['last_read_chat_at' => $messages->last()?->created_at ?? now()]);
        // Opening the conversation acknowledges chat and settlement-conversation alerts.
        TrackerNotification::where('user_id', $request->user()->id)
            ->where('tracker_id', $tracker->id)
            ->whereNull('dismissed_at')->whereNull('read_at')
            ->whereIn('type', ['conversation.message', 'settlement.requested', 'settlement.approved', 'settlement.declined'])
            ->update(['read_at' => now()]);
        $members = collect($cache->activeMembers($tracker))->keyBy('id');
        return Inertia::render('Trackers/Conversation', [
            'tracker' => $tracker,
            'messages' => $messages->map(fn ($message) => $this->messageData($message, $request->user()->id)),
            'hasMoreMessages' => $hasMoreMessages,
            'currentUserId' => $request->user()->id,
            'canChat' => $request->user()->can('chat', $tracker),
            'settlementOptions' => collect($finance->directDebts($tracker))->where('from_user_id', $request->user()->id)->map(fn ($debt) => ['to_user_id' => $debt['to_user_id'], 'to_name' => $members->get($debt['to_user_id'])['name'] ?? 'Member', 'amount_minor' => $debt['amount_minor']])->values(),
        ]);
    }

    public function older(Request $request, Tracker $tracker): JsonResponse
    {
        $this->authorize('view', $tracker);
        $data = $request->validate(['before' => ['required', 'date']]);
        $messages = $this->messageQuery($tracker)->where('created_at', '<', $data['before'])->latest('created_at')->limit(11)->get();
        return response()->json(['messages' => $messages->take(10)->sortBy('created_at')->values()->map(fn ($message) => $this->messageData($message, $request->user()->id)), 'has_more' => $messages->count() > 10]);
    }

    public function presence(Request $request, Tracker $tracker, ConversationPresence $presence): JsonResponse
    {
        abort_unless($request->user()->can('view', $tracker), 403);
        if ($request->boolean('active')) {
            $presence->mark($request->user()->id, $tracker);
        } else {
            $presence->forget($request->user()->id, $tracker);
        }

        return response()->json(['ok' => true]);
    }

    public function store(Request $request, Tracker $tracker, TrackerNotifier $notifier): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->can('chat', $tracker), 403);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:2000'], 'attachment' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'], 'client_message_id' => ['nullable', 'uuid']]);
        abort_if(!trim($data['body'] ?? '') && ! $request->hasFile('attachment'), 422, 'Write a message or choose an attachment.');
        $message = DB::transaction(function () use ($request, $tracker, $data) {
            $message = TrackerMessage::create(['tracker_id' => $tracker->id, 'user_id' => $request->user()->id, 'body' => trim($data['body'] ?? '')]);
            if ($file = $request->file('attachment')) {
                $message->attachments()->create(['disk' => 'local', 'path' => $file->store("tracker-conversations/{$tracker->id}", 'local'), 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize()]);
            }
            return $message;
        })->load(['author:id,name', 'attachments']);
        TrackerMember::where('tracker_id', $tracker->id)->where('user_id', $request->user()->id)->where('status', 'active')->update(['last_read_chat_at' => $message->created_at]);
        TrackerMessageCreated::dispatch($message, $data['client_message_id'] ?? null);
        $notifier->members($tracker, $request->user(), 'conversation.message', 'New message in '.$tracker->name, trim($message->body) ?: 'Sent an attachment', route('trackers.conversation.index', $tracker), ['message_id' => $message->id]);
        if ($request->expectsJson()) {
            return response()->json([
                'client_message_id' => $data['client_message_id'] ?? null,
                'message' => $this->messageData($message, $request->user()->id),
            ], 201);
        }
        return back();
    }

    public function requestSettlement(Request $request, Tracker $tracker, TrackerFinance $finance, TrackerNotifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->can('chat', $tracker), 403);
        $data = $request->validate(['to_user_id' => ['required', 'integer'], 'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'], 'settlement_date' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:1000']]);
        $amount = $this->minor($data['amount']);
        if ($amount <= 0) return back()->withErrors(['amount' => 'The settlement amount must be greater than zero.']);
        abort_unless($tracker->members()->where('user_id', $data['to_user_id'])->where('status', 'active')->exists(), 422);
        $debt = collect($finance->directDebts($tracker, false))->first(fn ($item) => $item['from_user_id'] === $request->user()->id && $item['to_user_id'] === (int) $data['to_user_id']);
        abort_unless($debt && $amount <= $debt['amount_minor'], 422);
        $message = DB::transaction(function () use ($request, $tracker, $data, $amount) {
            $settlementRequest = TrackerSettlementRequest::create(['tracker_id' => $tracker->id, 'from_user_id' => $request->user()->id, 'to_user_id' => $data['to_user_id'], 'amount_minor' => $amount, 'settlement_date' => $data['settlement_date'], 'note' => $data['note'] ?? null]);
            return TrackerMessage::create(['tracker_id' => $tracker->id, 'user_id' => $request->user()->id, 'body' => '', 'type' => 'settlement_request', 'settlement_request_id' => $settlementRequest->id]);
        })->load(['author:id,name', 'attachments', 'settlementRequest.fromUser:id,name', 'settlementRequest.toUser:id,name']);
        TrackerMessageCreated::dispatch($message);
        $notifier->user($message->settlementRequest->to_user_id, $tracker, $request->user()->id, 'settlement.requested', 'Settlement approval requested', $request->user()->name.' requested approval for a settlement in '.$tracker->name, route('trackers.conversation.index', $tracker));
        return back();
    }

    public function respondToSettlement(Request $request, Tracker $tracker, TrackerSettlementRequest $settlementRequest, TrackerFinance $finance, TrackerNotifier $notifier): RedirectResponse
    {
        abort_unless($settlementRequest->tracker_id === $tracker->id && $settlementRequest->status === 'pending' && $settlementRequest->to_user_id === $request->user()->id, 403);
        $data = $request->validate(['decision' => ['required', 'in:approved,declined']]);
        DB::transaction(function () use ($data, $settlementRequest, $request, $tracker, $finance) {
            if ($data['decision'] === 'declined') { $settlementRequest->update(['status' => 'declined', 'responded_by' => $request->user()->id, 'responded_at' => now()]); return; }
            $debt = collect($finance->directDebts($tracker, false))->first(fn ($item) => $item['from_user_id'] === $settlementRequest->from_user_id && $item['to_user_id'] === $settlementRequest->to_user_id);
            abort_unless($debt && $settlementRequest->amount_minor <= $debt['amount_minor'], 422);
            $settlement = Settlement::create(['tracker_id' => $tracker->id, 'from_user_id' => $settlementRequest->from_user_id, 'to_user_id' => $settlementRequest->to_user_id, 'amount_minor' => $settlementRequest->amount_minor, 'settlement_date' => $settlementRequest->settlement_date, 'note' => $settlementRequest->note, 'created_by' => $settlementRequest->from_user_id]);
            $settlementRequest->update(['status' => 'approved', 'approved_settlement_id' => $settlement->id, 'responded_by' => $request->user()->id, 'responded_at' => now()]);
        });
        $finance->forget($tracker);
        TrackerSettlementRequestUpdated::dispatch($settlementRequest->fresh());
        $notifier->user($settlementRequest->from_user_id, $tracker, $request->user()->id, 'settlement.'.$settlementRequest->status, 'Settlement '.$settlementRequest->status, $request->user()->name.' '.$settlementRequest->status.' your settlement request in '.$tracker->name, route('trackers.conversation.index', $tracker));
        return back();
    }

    public function attachment(Request $request, Tracker $tracker, TrackerMessage $message, TrackerMessageAttachment $attachment)
    {
        $this->authorize('view', $tracker);
        abort_unless($message->tracker_id === $tracker->id && $attachment->tracker_message_id === $message->id, 404);
        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"']);
    }

    public function react(Request $request, Tracker $tracker, TrackerMessage $message): RedirectResponse
    {
        abort_unless($message->tracker_id === $tracker->id && $request->user()->can('chat', $tracker), 403);
        $data = $request->validate(['emoji' => ['required', 'string', 'in:❤️,😂,😮,😢,👍']]);
        $reaction = $message->reactions()->where('user_id', $request->user()->id)->first();
        if ($reaction?->emoji === $data['emoji']) $reaction->delete(); else $message->reactions()->updateOrCreate(['user_id' => $request->user()->id], ['emoji' => $data['emoji']]);
        TrackerMessageReactionUpdated::dispatch($message);
        return back();
    }

    private function messageData(TrackerMessage $message, int $userId): array
    {
        return ['id' => $message->id, 'body' => $message->body, 'type' => $message->type, 'created_at' => $message->created_at, 'author' => ['id' => $message->author->id, 'name' => $message->author->name], 'attachments' => $message->attachments->map(fn ($attachment) => ['id' => $attachment->id, 'name' => $attachment->original_name, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes, 'url' => route('trackers.conversation.attachments.show', [$message->tracker_id, $message->id, $attachment->id])])->values(), 'settlement_request' => $message->settlementRequest ? ['id' => $message->settlementRequest->id, 'from_name' => $message->settlementRequest->fromUser->name, 'to_name' => $message->settlementRequest->toUser->name, 'from_user_id' => $message->settlementRequest->from_user_id, 'to_user_id' => $message->settlementRequest->to_user_id, 'amount_minor' => $message->settlementRequest->amount_minor, 'settlement_date' => $message->settlementRequest->settlement_date, 'note' => $message->settlementRequest->note, 'status' => $message->settlementRequest->status] : null, 'reactions' => $message->reactions->groupBy('emoji')->map(fn ($reactions, $emoji) => ['emoji' => $emoji, 'count' => $reactions->count(), 'reacted_by_me' => $reactions->contains('user_id', $userId)])->values()];
    }

    private function minor(string $amount): int { [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, ''); return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0'); }
    private function messageQuery(Tracker $tracker) { return $tracker->messages()->with(['author:id,name', 'reactions:id,tracker_message_id,user_id,emoji', 'attachments', 'settlementRequest.fromUser:id,name', 'settlementRequest.toUser:id,name']); }
}
