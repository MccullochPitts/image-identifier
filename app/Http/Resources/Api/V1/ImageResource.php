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
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);

        // Check if disk uses S3 driver (includes Cloudflare R2)
        // Laravel Cloud injects disks with driver 's3' but custom names like 'private'
        $diskConfig = config("filesystems.disks.{$diskName}");
        $usesS3Driver = isset($diskConfig['driver']) && $diskConfig['driver'] === 's3';

        // Generate URLs based on disk type (signed for S3/R2, regular for local/public)
        $url = null;
        $thumbnailUrl = null;

        if ($this->path) {
            $url = $usesS3Driver
                ? $disk->temporaryUrl($this->path, now()->addHour())
                : $disk->url($this->path);
        }

        if ($this->thumbnail_path) {
            $thumbnailUrl = $usesS3Driver
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
