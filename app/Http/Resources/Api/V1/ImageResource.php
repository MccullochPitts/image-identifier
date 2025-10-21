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
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'url' => $this->path ? Storage::disk(config('filesystems.default'))->url($this->path) : null,
            'thumbnail_url' => $this->thumbnail_path ? Storage::disk(config('filesystems.default'))->url($this->thumbnail_path) : null,
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
