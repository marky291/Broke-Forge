<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchemaRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9_]+$/', // Only alphanumeric and underscores
                Rule::unique('server_database_schemas', 'name')
                    ->where('server_database_id', $database->id),
            ],
            'user' => [
                'nullable',
                'string',
                'max:64',
                'required_with:password',
                Rule::unique('server_database_users', 'username')
                    ->where('server_database_id', $database->id),
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'required_with:user',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The schema name is required.',
            'name.regex' => 'The schema name may only contain letters, numbers, and underscores.',
            'name.unique' => 'A schema with this name already exists for this database.',
            'user.required_with' => 'A username is required when providing a password.',
            'user.unique' => 'A user with this username already exists for this database.',
            'password.required_with' => 'A password is required when providing a username.',
            'password.min' => 'The password must be at least 8 characters.',
        ];
    }
}
