<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracker_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('tracker_id')->nullable();
            $table->foreign('tracker_id')->references('id')->on('trackers')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 100);
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->string('url', 2048)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'dismissed_at', 'read_at', 'created_at'], 'tracker_notifications_inbox_idx');
            $table->index(['user_id', 'tracker_id', 'dismissed_at', 'read_at'], 'tracker_notifications_badge_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('tracker_notifications'); }
};
