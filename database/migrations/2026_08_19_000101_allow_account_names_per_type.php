<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personal_accounts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->unique(['user_id', 'name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('personal_accounts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name', 'type']);
            $table->unique(['user_id', 'name']);
        });
    }
};
