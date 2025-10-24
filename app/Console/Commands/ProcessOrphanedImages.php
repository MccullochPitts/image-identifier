<?php

namespace App\Console\Commands;

use App\Jobs\BatchProcessImages;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOrphanedImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:process-orphaned
                            {--threshold=15 : Minutes after which processing images are considered orphaned}
                            {--dispatch : Dispatch BatchProcessImages job after resetting orphaned images}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset orphaned images stuck in processing status and optionally trigger batch processing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $thresholdMinutes = (int) $this->option('threshold');
        $shouldDispatch = $this->option('dispatch');

        $this->info("Checking for images stuck in 'processing' status for more than {$thresholdMinutes} minutes...");

        // Find images stuck in processing status
        $orphanedImages = Image::where('processing_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($thresholdMinutes))
            ->get();

        if ($orphanedImages->isEmpty()) {
            $this->info('No orphaned images found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$orphanedImages->count()} orphaned images.");

        // Reset each orphaned image to pending
        foreach ($orphanedImages as $image) {
            $image->update([
                'processing_status' => 'pending',
                'batch_id' => null,
            ]);

            Log::warning('Orphaned image reset to pending', [
                'image_id' => $image->id,
                'stuck_since' => $image->updated_at,
                'batch_id' => $image->batch_id,
            ]);
        }

        $this->info("Reset {$orphanedImages->count()} images to 'pending' status.");

        // Optionally dispatch batch processing
        if ($shouldDispatch) {
            dispatch(new BatchProcessImages);
            $this->info('Dispatched BatchProcessImages job to process pending images.');
        }

        return self::SUCCESS;
    }
}
