<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\TagService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTags implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<string>|null  $requestedKeys  Optional specific tag keys to generate
     */
    public function __construct(
        public Image $image,
        public ?array $requestedKeys = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TagService $tagService): void
    {
        $tagService->generateTags($this->image, $this->requestedKeys);
    }
}
