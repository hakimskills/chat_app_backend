<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'string',
                'max:30',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'phone_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone_number')->ignore($userId),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}