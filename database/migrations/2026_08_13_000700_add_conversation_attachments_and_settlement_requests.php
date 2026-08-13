<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_settlement_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_id');
            $table->foreign('tracker_id')->references('id')->on('trackers')->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->date('settlement_date');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_settlement_id')->nullable()->constrained('settlements')->nullOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['tracker_id', 'status', 'created_at']);
            $table->index(['to_user_id', 'status']);
        });

        Schema::table('tracker_messages', function (Blueprint $table) {
            $table->string('type', 30)->default('text')->after('body');
            $table->uuid('settlement_request_id')->nullable()->after('type');
            $table->foreign('settlement_request_id')->references('id')->on('tracker_settlement_requests')->nullOnDelete();
            $table->index(['tracker_id', 'type', 'created_at'], 'tracker_messages_type_thread_idx');
        });

        Schema::create('tracker_message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_message_id');
            $table->foreign('tracker_message_id')->references('id')->on('tracker_messages')->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->index('tracker_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_message_attachments');
        Schema::table('tracker_messages', function (Blueprint $table) {
            $table->dropIndex('tracker_messages_type_thread_idx');
            $table->dropForeign(['settlement_request_id']);
            $table->dropColumn(['type', 'settlement_request_id']);
        });
        Schema::dropIfExists('tracker_settlement_requests');
    }
};
