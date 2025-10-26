<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get the configured disk
        $disk = Storage::disk(config('filesystems.default'));
        $diskName = config('filesystems.default');

        // Generate URLs based on disk type (signed for S3, regular for local/public)
        $url = null;
        $thumbnailUrl = null;

        if ($this->path) {
            $url = $diskName === 's3'
                ? $disk->temporaryUrl($this->path, now()->addHour())
                : $disk->url($this->path);
        }

        if ($this->thumbnail_path) {
            $thumbnailUrl = $diskName === 's3'
                ? $disk->temporaryUrl($this->thumbnail_path, now()->addHour())
                : $disk->url($this->thumbnail_path);
        }

        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'hash' => $this->hash,
            'processing_status' => $this->processing_status,
            'type' => $this->type,
            'parent_id' => $this->parent_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'children' => ImageResource::collection($this->whenLoaded('children')),
        ];
    }
}
