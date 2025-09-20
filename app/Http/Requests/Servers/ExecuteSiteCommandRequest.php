<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteSiteCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'command' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'command.required' => 'Please provide a command to run.',
        ];
    }
}
