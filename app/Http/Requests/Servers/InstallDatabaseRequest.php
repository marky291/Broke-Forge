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
        return [
            'type' => ['required', 'string', Rule::in(['mysql', 'postgresql', 'redis'])],
            'version' => ['required', 'string'],
            'root_password' => ['nullable', 'string', 'min:8'],
            'password' => ['nullable', 'string', 'min:8'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Please select a database type.',
            'type.in' => 'Invalid database type selected.',
            'version.required' => 'Please select a database version.',
            'root_password.min' => 'Root password must be at least 8 characters.',
            'password.min' => 'Password must be at least 8 characters.',
            'port.min' => 'Port must be at least 1.',
            'port.max' => 'Port cannot exceed 65535.',
        ];
    }
}
