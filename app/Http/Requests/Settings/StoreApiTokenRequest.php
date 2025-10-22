<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('personal_access_tokens', 'name')->where(function ($query) {
                    return $query->where('tokenable_id', $this->user()->id)
                        ->where('tokenable_type', get_class($this->user()));
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a token with this name.',
        ];
    }
}
