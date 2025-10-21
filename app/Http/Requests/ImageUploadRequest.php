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
        $imageCount = is_array($this->file('images')) ? count($this->file('images')) : 1;

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
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // Max 10MB per image
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
            'images.*.required' => 'Each image file is required.',
            'images.*.image' => 'Each file must be a valid image.',
            'images.*.mimes' => 'Images must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'images.*.max' => 'Each image must not exceed 10MB.',
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
