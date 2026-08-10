<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFriendRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => ['required_without:username', 'integer', 'exists:users,id'],
            'username' => ['required_without:recipient_id', 'string', 'exists:users,username'],
        ];
    }
}