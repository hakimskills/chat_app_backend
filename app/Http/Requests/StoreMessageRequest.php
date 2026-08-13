<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Either a body or an image is required — never both empty,
            // but a captioned image (both present) is fine too.
            'body' => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'image' => ['required_without:body', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],

            'reply_to_message_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where(
                    fn ($query) => $query->where('conversation_id', $this->route('conversation')?->id)
                ),
            ],
        ];
    }
}