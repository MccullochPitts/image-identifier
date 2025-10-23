<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Checks if the user has sufficient upload quota for the requested number of images.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // User must be authenticated
        if (! $user) {
            return false;
        }

        // Get the number of images being uploaded
        $imageCount = is_array($this->input('images')) ? count($this->input('images')) : 1;

        // Check if user has quota for ALL images in this request (all-or-nothing)
        if ($user->remainingUploads() < $imageCount) {
            // Add custom error message to validation errors
            $this->failedAuthorization();
        }

        return $user->remainingUploads() >= $imageCount;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'images' => 'required|array|min:1|max:50', // Allow up to 50 images at once
            'images.*.file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // Max 10MB per image
            'images.*.description' => 'nullable|string|max:5000',
            'images.*.tags' => 'nullable|array',
            'images.*.tags.*' => 'required|string|max:255', // Tag values must be strings
            'images.*.requested_tags' => 'nullable|array',
            'images.*.requested_tags.*' => 'required|string|max:100', // Tag keys must be strings
        ];
    }

    /**
     * Get custom error messages for validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Please provide at least one image to upload.',
            'images.array' => 'Images must be provided as an array.',
            'images.min' => 'Please provide at least one image to upload.',
            'images.max' => 'You can upload a maximum of 50 images at once.',
            'images.*.file.required' => 'Each image file is required.',
            'images.*.file.image' => 'Each file must be a valid image.',
            'images.*.file.mimes' => 'Images must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'images.*.file.max' => 'Each image must not exceed 10MB.',
            'images.*.description.string' => 'Description must be text.',
            'images.*.description.max' => 'Description must not exceed 5000 characters.',
            'images.*.tags.array' => 'Tags must be provided as key-value pairs.',
            'images.*.tags.*.string' => 'Tag values must be text.',
            'images.*.tags.*.max' => 'Tag values must not exceed 255 characters.',
            'images.*.requested_tags.array' => 'Requested tags must be an array.',
            'images.*.requested_tags.*.string' => 'Requested tag keys must be text.',
            'images.*.requested_tags.*.max' => 'Requested tag keys must not exceed 100 characters.',
        ];
    }

    /**
     * Handle a failed authorization attempt with custom message.
     */
    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $remaining = $user->remainingUploads();
        $limit = $user->uploadLimit();

        throw new \Illuminate\Auth\Access\AuthorizationException(
            "Upload quota exceeded. You have {$remaining} of {$limit} uploads remaining this billing period."
        );
    }
}
