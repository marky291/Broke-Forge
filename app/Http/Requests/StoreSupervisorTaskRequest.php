<?php

namespace App\Http\Requests;

use App\Packages\Services\Sites\Command\Rules\ValidPhpCommand;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupervisorTaskRequest extends FormRequest
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
        $server = $this->route('server');

        return [
            'name' => ['required', 'string', 'max:255'],
            'command' => ['required', 'string', 'max:1000', new ValidPhpCommand($server)],
            'working_directory' => ['required', 'string', 'max:500'],
            'processes' => ['required', 'integer', 'min:1', 'max:20'],
            'user' => ['required', 'string', 'max:255'],
            'auto_restart' => ['required', 'boolean'],
            'autorestart_unexpected' => ['required', 'boolean'],
        ];
    }
}
