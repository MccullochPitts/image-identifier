<?php

use Database\Seeders\SystemDefaultEmbeddingConfigSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Run the seeder via migration (since migrations run automatically on Laravel Cloud)
        $seeder = new SystemDefaultEmbeddingConfigSeeder;
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional: Could delete the config if needed
        // App\Models\EmbeddingConfiguration::where('scope', 'system_default')->delete();
    }
};
