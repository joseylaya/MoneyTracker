<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracker_members', function (Blueprint $table) {
            $table->timestamp('last_read_chat_at')->nullable()->after('removed_at');
            $table->index(['user_id', 'status', 'tracker_id'], 'tracker_members_user_status_tracker_idx');
        });

        Schema::table('tracker_messages', function (Blueprint $table) {
            $table->index(['tracker_id', 'deleted_at', 'created_at'], 'tracker_messages_thread_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['tracker_id', 'deleted_at', 'expense_date'], 'expenses_tracker_active_date_idx');
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->index(['tracker_id', 'deleted_at', 'settlement_date'], 'settlements_tracker_active_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', fn (Blueprint $table) => $table->dropIndex('settlements_tracker_active_date_idx'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropIndex('expenses_tracker_active_date_idx'));
        Schema::table('tracker_messages', fn (Blueprint $table) => $table->dropIndex('tracker_messages_thread_idx'));
        Schema::table('tracker_members', function (Blueprint $table) {
            $table->dropIndex('tracker_members_user_status_tracker_idx');
            $table->dropColumn('last_read_chat_at');
        });
    }
};
