<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_id');
            $table->foreign('tracker_id')->references('id')->on('trackers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tracker_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_messages');
    }
};
