<?php

namespace App\Events;

use App\Models\ExpenseComment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseCommentCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public ExpenseComment $comment) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tracker.'.$this->comment->expense->tracker_id)];
    }

    public function broadcastAs(): string { return 'expense.comment.created'; }

    public function broadcastWith(): array
    {
        return ['comment' => [
            'id' => $this->comment->id,
            'expense_id' => $this->comment->expense_id,
            'body' => $this->comment->body,
            'created_at' => $this->comment->created_at?->toIso8601String(),
            'author' => ['id' => $this->comment->author->id, 'name' => $this->comment->author->name],
        ]];
    }
}
