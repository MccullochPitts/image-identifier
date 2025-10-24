<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Index for sorting images by recency (used in latest() queries)
            $table->index('created_at');

            // Composite index for optimizing the most common query pattern:
            // "get user's images sorted by latest"
            // This covers: WHERE user_id = ? ORDER BY created_at DESC
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('tags', function (Blueprint $table) {
            // Index for filtering tags by key (e.g., "get all 'character' tags")
            // Useful for future tag filtering and analytics
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex(['key']);
        });
    }
};
