<?php

namespace App\Http\Requests\Servers;

use App\Enums\ServerProvider;
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
            'provider' => ['nullable', Rule::enum(ServerProvider::class)],
            'public_ip' => ['required', 'ip', Rule::unique('servers', 'public_ip')],
            'private_ip' => ['nullable', 'ip'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'php_version' => ['required', Rule::in(['8.1', '8.2', '8.3', '8.4'])],
            'add_ssh_key_to_github' => ['nullable', 'boolean'],
        ];
    }
}
