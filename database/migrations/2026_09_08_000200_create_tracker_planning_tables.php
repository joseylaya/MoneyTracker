<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tracker_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['tracker_id', 'completed_at']);
        });

        Schema::create('tracker_planned_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tracker_id')->constrained()->cascadeOnDelete();
            $table->string('description', 180);
            $table->unsignedBigInteger('estimated_amount_minor');
            $table->date('expected_date')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['tracker_id', 'expected_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_planned_expenses');
        Schema::dropIfExists('tracker_tasks');
    }
};
