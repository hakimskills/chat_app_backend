<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Default to a private (1-on-1) conversation when type is omitted.
        $this->merge(['type' => $this->input('type', 'private')]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:private,group'],

            // Private conversations
            'user_id' => ['required_if:type,private', 'integer', 'exists:users,id'],

            // Group conversations
            'name' => ['required_if:type,group', 'string', 'max:255'],
            'participant_ids' => ['required_if:type,group', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}