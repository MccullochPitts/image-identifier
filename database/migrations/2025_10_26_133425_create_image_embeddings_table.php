<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('image_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained()->onDelete('cascade');
            $table->foreignId('embedding_configuration_id')->nullable()->constrained('embedding_configurations')->onDelete('cascade');
            $table->string('embedding_type'); // 'visual' or 'semantic'
            $table->text('source_text')->nullable();
            $table->timestamps();

            $table->index('image_id');
            $table->index('embedding_configuration_id');
            $table->index('embedding_type');
            $table->unique(['image_id', 'embedding_configuration_id', 'embedding_type']);
        });

        // Add vector column using raw SQL (Cohere embed-english-v3.0 uses 1024 dimensions)
        DB::statement('ALTER TABLE image_embeddings ADD COLUMN vector vector(1024)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_embeddings');
    }
};
