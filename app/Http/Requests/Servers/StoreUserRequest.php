<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        $database = $this->route('database');

        return [
            'username' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-zA-Z0-9_]+$/', // Only alphanumeric and underscores
                Rule::unique('server_database_users')
                    ->where('server_database_id', $database->id)
                    ->where('host', $this->input('host', '%')),
            ],
            'password' => ['required', 'string', 'min:8'],
            'host' => ['nullable', 'string', 'max:255'], // %, localhost, or IP address
            'privileges' => ['required', 'string', 'in:all,read_only,read_write'],
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
            'username.required' => 'The username is required.',
            'username.regex' => 'The username may only contain letters, numbers, and underscores.',
            'username.unique' => 'A user with this username and host already exists for this database.',
            'password.required' => 'The password is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'privileges.required' => 'Please select a privilege level.',
            'privileges.in' => 'Invalid privilege level selected.',
        ];
    }
}
