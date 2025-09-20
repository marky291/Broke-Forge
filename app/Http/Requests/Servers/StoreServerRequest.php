<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'vanity_name' => ['required', 'string', 'max:100'],
            'public_ip' => ['required', 'ip', Rule::unique('servers', 'public_ip')],
            'private_ip' => ['nullable', 'ip'],
            'php_version' => ['required', Rule::in(['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'])],
        ];
    }
}
