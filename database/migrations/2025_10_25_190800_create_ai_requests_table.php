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
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->string('model'); // e.g., "gemini-2.0-flash-exp"
            $table->string('action'); // e.g., "generate_tags", "batch_generate_tags"
            $table->integer('prompt_tokens');
            $table->integer('completion_tokens');
            $table->integer('total_tokens');
            $table->integer('cached_tokens')->nullable(); // For Gemini 2.5+ caching
            $table->decimal('cost_estimate', 10, 6)->nullable(); // USD cost estimate
            $table->json('metadata')->nullable(); // Store request details (image_ids, batch_size, etc.)
            $table->timestamps();

            // Indexes for common queries
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
