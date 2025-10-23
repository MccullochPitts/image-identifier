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
     * Get the validated data from the request - override to restructure multipart data.
     *
     * This handles both JSON (for tests) and multipart/form-data (for real API calls).
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Check if images[0] is already an array with 'file' key (JSON format from tests)
        if (isset($validated['images'][0]) && is_array($validated['images'][0]) && isset($validated['images'][0]['file'])) {
            // Already in correct format, just return
            return $validated;
        }

        // Check if images[0] is an UploadedFile (multipart format from Postman)
        if (isset($validated['images'][0]) && $validated['images'][0] instanceof \Illuminate\Http\UploadedFile) {
            // Restructure multipart/form-data format
            $imagesData = $this->input('images_data', []);
            $restructured = [];

            foreach ($validated['images'] as $index => $file) {
                $imageData = ['file' => $file];

                // Add metadata if provided for this index
                if (isset($imagesData[$index])) {
                    $data = $imagesData[$index];

                    if (isset($data['description'])) {
                        $imageData['description'] = $data['description'];
                    }

                    if (isset($data['tags'])) {
                        $imageData['tags'] = $data['tags'];
                    }

                    if (isset($data['requested_tags'])) {
                        $imageData['requested_tags'] = $data['requested_tags'];
                    }
                }

                $restructured[] = $imageData;
            }

            $validated['images'] = $restructured;
        }

        return $validated;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Detect format: if first image is an array, it's JSON format
        // Otherwise it's multipart format with files directly
        $images = $this->input('images', []);
        $isJsonFormat = ! empty($images) && is_array($images[0]) && ! ($images[0] instanceof \Illuminate\Http\UploadedFile);

        if ($isJsonFormat) {
            // JSON format: images[0]['file']
            return [
                'images' => 'required|array|min:1|max:50',
                'images.*.file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
                'images.*.description' => 'nullable|string|max:5000',
                'images.*.tags' => 'nullable|array',
                'images.*.tags.*' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        // Support both string values and array of strings (for multi-value tags)
                        if (is_string($value)) {
                            if (strlen($value) > 255) {
                                $fail("The {$attribute} must not exceed 255 characters.");
                            }
                        } elseif (is_array($value)) {
                            foreach ($value as $item) {
                                if (! is_string($item)) {
                                    $fail("Each {$attribute} array item must be a string.");

                                    return;
                                }
                                if (strlen($item) > 255) {
                                    $fail("Each {$attribute} array item must not exceed 255 characters.");

                                    return;
                                }
                            }
                        } else {
                            $fail("The {$attribute} must be a string or array of strings.");
                        }
                    },
                ],
                'images.*.requested_tags' => 'nullable|array',
                'images.*.requested_tags.*' => 'required|string|max:100',
            ];
        } else {
            // Multipart format: images[0] (file directly)
            return [
                'images' => 'required|array|min:1|max:50',
                'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
                'images_data.*.description' => 'nullable|string|max:5000',
                'images_data.*.tags' => 'nullable|array',
                'images_data.*.tags.*' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        // Support both string values and array of strings (for multi-value tags)
                        if (is_string($value)) {
                            if (strlen($value) > 255) {
                                $fail("The {$attribute} must not exceed 255 characters.");
                            }
                        } elseif (is_array($value)) {
                            foreach ($value as $item) {
                                if (! is_string($item)) {
                                    $fail("Each {$attribute} array item must be a string.");

                                    return;
                                }
                                if (strlen($item) > 255) {
                                    $fail("Each {$attribute} array item must not exceed 255 characters.");

                                    return;
                                }
                            }
                        } else {
                            $fail("The {$attribute} must be a string or array of strings.");
                        }
                    },
                ],
                'images_data.*.requested_tags' => 'nullable|array',
                'images_data.*.requested_tags.*' => 'required|string|max:100',
            ];
        }
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
