<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('personal_transactions', function (Blueprint $table) {
            $table->bigInteger('bill_reserved_minor')->nullable()->after('amount_minor');
            $table->date('bill_cycle_date')->nullable()->after('bill_reserved_minor');
            $table->index(['user_id', 'bill_cycle_date']);
        });
    }

    public function down(): void {
        Schema::table('personal_transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'bill_cycle_date']);
            $table->dropColumn(['bill_reserved_minor', 'bill_cycle_date']);
        });
    }
};
