<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trackers', function (Blueprint $table) {
            $table->uuid('share_token')->nullable()->unique()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('trackers', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
