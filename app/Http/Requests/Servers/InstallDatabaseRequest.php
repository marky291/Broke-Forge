<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $server = $this->route('server');

        return [
            'name' => ['nullable', 'string', 'max:64'],
            'type' => ['required', Rule::enum(\App\Enums\DatabaseType::class)],
            'version' => ['required', 'string', 'max:16'],
            'root_password' => ['required', 'string', 'min:8', 'max:128'],
            'port' => [
                'nullable',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('server_databases', 'port')
                    ->where('server_id', $server?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Please select a database type.',
            'version.required' => 'Please select a database version.',
            'root_password.required' => 'Root password is required.',
            'root_password.min' => 'Root password must be at least 8 characters.',
            'port.min' => 'Port must be at least 1.',
            'port.max' => 'Port cannot exceed 65535.',
            'port.unique' => 'This port is already in use by another database on this server.',
        ];
    }
}
