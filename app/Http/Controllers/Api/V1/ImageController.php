<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Resources\Api\V1\ImageResource;
use App\Models\Image;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImageController extends Controller
{
    public function __construct(protected ImageService $imageService) {}

    /**
     * Display a listing of the user's images.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['type', 'processing_status', 'parent_id']);
        $images = $this->imageService->getUserImages($request->user(), $filters);

        return ImageResource::collection($images);
    }

    /**
     * Store newly uploaded images.
     */
    public function store(ImageUploadRequest $request): JsonResponse
    {
        $uploadedImages = $this->imageService->uploadImages(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => ImageResource::collection($uploadedImages),
        ], 201);
    }

    /**
     * Display the specified image.
     */
    public function show(Request $request, Image $image): ImageResource
    {
        // Ensure user owns this image
        if ($image->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        return new ImageResource($image->load(['children']));
    }

    /**
     * Remove the specified image.
     */
    public function destroy(Request $request, Image $image): JsonResponse
    {
        // Ensure user owns this image
        if ($image->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        $this->imageService->deleteImage($image);

        return response()->json(['message' => 'Image deleted successfully']);
    }
}
