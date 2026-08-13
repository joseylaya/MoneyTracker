<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_message_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_message_id');
            $table->foreign('tracker_message_id')->references('id')->on('tracker_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['tracker_message_id', 'user_id']);
            $table->index(['tracker_message_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_message_reactions');
    }
};
