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
            'body' => ['required_without_all:image,audio', 'nullable', 'string', 'max:5000'],
            'image' => ['required_without_all:body,audio', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],

            // Broadened to cover how different encoders/containers report
            // m4a/AAC recordings — libmagic's exact string for the same
            // file can vary by platform and version (audio/mp4 vs
            // video/mp4 vs audio/x-m4a are all seen in the wild for the
            // same AAC-LC .m4a container).
            'audio' => [
                'required_without_all:body,image',
                'nullable',
                'file',
                'mimetypes:audio/mpeg,audio/mp4,video/mp4,audio/x-m4a,audio/aac,audio/wav,audio/x-wav,audio/ogg,audio/webm',
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