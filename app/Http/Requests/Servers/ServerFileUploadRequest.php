<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class ServerFileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['nullable', 'string', 'max:4096'],
            'file' => ['required', 'file', 'max:51200'],
        ];
    }
}
