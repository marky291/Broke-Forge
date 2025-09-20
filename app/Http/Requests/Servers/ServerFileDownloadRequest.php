<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class ServerFileDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:4096'],
        ];
    }
}
