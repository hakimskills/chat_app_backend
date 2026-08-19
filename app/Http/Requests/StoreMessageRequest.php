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
            // Exactly one of body/image/audio must be present (a captioned
            // image is fine — body + image together — but audio messages
            // don't currently support a caption).
            'body' => ['required_without_all:image,audio', 'nullable', 'string', 'max:5000'],
            'image' => ['required_without_all:body,audio', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],

            // mimetypes (not mimes) validates the actual MIME the client
            // sends — recorded audio blobs don't always carry a clean
            // extension, so extension-based sniffing is unreliable here.
            'audio' => [
                'required_without_all:body,image',
                'nullable',
                'file',
                'mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/wav,audio/ogg,audio/webm',
                'max:10240',
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],

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