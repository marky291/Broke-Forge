<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $serverId = $this->route('server')->id;

        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
                Rule::unique('sites')->where('server_id', $serverId),
            ],
            'php_version' => [
                'required',
                'string',
                'in:7.4,8.0,8.1,8.2,8.3',
            ],
            'ssl' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.required' => 'The domain name is required.',
            'domain.regex' => 'Please enter a valid domain name (e.g., example.com).',
            'domain.unique' => 'This domain is already configured on this server.',
            'php_version.required' => 'Please select a PHP version.',
            'php_version.in' => 'Please select a valid PHP version.',
            'ssl.required' => 'Please specify whether to enable SSL.',
        ];
    }
}
