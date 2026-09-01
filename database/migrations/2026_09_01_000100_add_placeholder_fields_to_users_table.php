<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_placeholder')->default(false)->after('remember_token');
            $table->foreignUuid('placeholder_tracker_id')->nullable()->after('is_placeholder')->constrained('trackers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('placeholder_tracker_id');
            $table->dropColumn('is_placeholder');
        });
    }
};
