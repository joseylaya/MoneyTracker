<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personal_finance_settings', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3)->default('PHP'); $table->unsignedTinyInteger('payday_day')->default(15);
            $table->decimal('lifestyle_percent', 5, 2)->default(15); $table->decimal('savings_percent', 5, 2)->default(0); $table->decimal('emergency_percent', 5, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('personal_accounts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('name', 100); $table->string('type', 30)->default('bank');
            $table->bigInteger('balance_minor')->default(0); $table->timestamp('last_reconciled_at')->nullable(); $table->timestamps(); $table->unique(['user_id','name']);
        });
        Schema::create('personal_buckets', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('type', 20); $table->bigInteger('target_minor')->default(0); $table->bigInteger('reserved_minor')->default(0); $table->timestamps(); $table->unique(['user_id','type']);
        });
        Schema::create('personal_commitments', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('name', 120); $table->bigInteger('amount_minor'); $table->unsignedTinyInteger('due_day');
            $table->string('frequency', 20)->default('monthly'); $table->string('category', 60)->default('Bills'); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('personal_transactions', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('personal_account_id')->nullable()->constrained('personal_accounts')->nullOnDelete();
            $table->foreignId('personal_commitment_id')->nullable()->constrained('personal_commitments')->nullOnDelete(); $table->string('type', 30); $table->bigInteger('amount_minor');
            $table->string('category', 60)->nullable(); $table->string('description', 255)->nullable(); $table->text('note')->nullable(); $table->date('occurred_on'); $table->uuid('transfer_group_id')->nullable(); $table->timestamps();
            $table->index(['user_id','occurred_on']); $table->index(['personal_account_id','occurred_on']);
        });
        Schema::create('personal_reconciliations', function (Blueprint $table) {
            $table->id(); $table->foreignId('personal_account_id')->constrained()->cascadeOnDelete(); $table->bigInteger('expected_minor'); $table->bigInteger('actual_minor'); $table->bigInteger('difference_minor'); $table->string('resolution', 40); $table->text('note')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('personal_reconciliations'); Schema::dropIfExists('personal_transactions'); Schema::dropIfExists('personal_commitments'); Schema::dropIfExists('personal_buckets'); Schema::dropIfExists('personal_accounts'); Schema::dropIfExists('personal_finance_settings'); }
};
