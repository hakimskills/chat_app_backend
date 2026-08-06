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
        // Text-only for now — attachments (image/video/file) get their own
        // upload endpoint later, which will relax the 'body' requirement.
        return [
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['sometimes', 'in:text'],

            // Must reference a message that exists AND belongs to this
            // same conversation — prevents replying across conversations.
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