<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trackers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('currency_code', 3)->default('PHP');
            $table->unsignedTinyInteger('currency_exponent')->default(2);
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tracker_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_id');
            $table->foreign('tracker_id')->references('id')->on('trackers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 30);
            $table->string('status', 30)->default('active');
            $table->timestamp('joined_at');
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tracker_id', 'user_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_id');
            $table->foreign('tracker_id')->references('id')->on('trackers')->restrictOnDelete();
            $table->string('description', 255);
            $table->unsignedBigInteger('amount_minor');
            $table->foreignId('paid_by_user_id')->constrained('users')->restrictOnDelete();
            $table->date('expense_date');
            $table->text('note')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tracker_id', 'expense_date']);
        });

        Schema::create('expense_splits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('expense_id');
            $table->foreign('expense_id')->references('id')->on('expenses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['expense_id', 'user_id']);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_id');
            $table->foreign('tracker_id')->references('id')->on('trackers')->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->date('settlement_date');
            $table->string('payment_method', 50)->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_id')->nullable();
            $table->foreign('tracker_id')->references('id')->on('trackers')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100);
            $table->string('subject_id')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at');
            $table->index(['tracker_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('expense_splits');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('tracker_members');
        Schema::dropIfExists('trackers');
    }
};
