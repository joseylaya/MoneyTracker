<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('expense_type', 20)->default('split')->after('note');
            $table->string('split_method', 20)->default('equal')->after('expense_type');
            $table->unsignedBigInteger('unit_price_minor')->nullable()->after('split_method');
        });
        Schema::table('expense_splits', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('expense_splits', fn (Blueprint $table) => $table->dropColumn('quantity'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn(['expense_type', 'split_method', 'unit_price_minor']));
    }
};
