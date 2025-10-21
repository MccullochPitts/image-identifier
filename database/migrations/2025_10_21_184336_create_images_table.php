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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // File size in bytes
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('hash', 64)->nullable()->index(); // For duplicate detection
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->enum('type', ['original', 'detected_item'])->default('original');
            $table->foreignId('parent_id')->nullable()->constrained('images')->cascadeOnDelete(); // For detected items
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();

            // Indexes for common queries
            $table->index('user_id');
            $table->index('type');
            $table->index('parent_id');
            $table->index('processing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
