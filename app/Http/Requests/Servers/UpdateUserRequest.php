<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'min:8'],
            'privileges' => ['nullable', 'string', 'in:all,read_only,read_write'],
            'schema_ids' => ['nullable', 'array'],
            'schema_ids.*' => ['integer', 'exists:server_database_schemas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'password.min' => 'The password must be at least 8 characters.',
            'privileges.in' => 'Invalid privilege level selected.',
        ];
    }
}
