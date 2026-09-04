<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tracker_id');
            $table->foreign('tracker_id')->references('id')->on('trackers')->cascadeOnDelete();
            $table->date('date');
            $table->string('title', 150)->nullable();
            $table->text('notes')->nullable();
            $table->string('route_mode', 20)->default('driving');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tracker_id', 'date']);
            $table->index(['tracker_id', 'sort_order']);
        });

        Schema::create('itinerary_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('itinerary_day_id');
            $table->foreign('itinerary_day_id')->references('id')->on('itinerary_days')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('type', 30)->default('place');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location_name', 255)->nullable();
            $table->string('location_address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['itinerary_day_id', 'sort_order']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->uuid('itinerary_item_id')->nullable()->after('tracker_id');
            $table->foreign('itinerary_item_id')->references('id')->on('itinerary_items')->nullOnDelete();
            $table->index('itinerary_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['itinerary_item_id']);
            $table->dropColumn('itinerary_item_id');
        });
        Schema::dropIfExists('itinerary_items');
        Schema::dropIfExists('itinerary_days');
    }
};
