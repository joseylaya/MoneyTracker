<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_id');
            $table->foreign('tracker_id')->references('id')->on('trackers')->restrictOnDelete();
            $table->string('email')->index();
            $table->string('role', 30)->default('viewer');
            $table->string('status', 30)->default('pending');
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['tracker_id', 'email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_invitations');
    }
};
