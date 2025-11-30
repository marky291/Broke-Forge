<?php

namespace App\Http\Requests\Servers;

use App\Enums\DatabaseEngine;
use App\Services\DatabaseConfigurationService;
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
        $engine = DatabaseEngine::tryFrom($this->input('engine'));
        $isRedis = $engine === DatabaseEngine::Redis;

        // Name and password are not needed for Redis (cache/queue services)
        $nameRules = $isRedis
            ? ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/']
            : ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'];

        $passwordRules = $isRedis
            ? ['nullable', 'string', 'min:8', 'max:128']
            : ['required', 'string', 'min:8', 'max:128'];

        return [
            'name' => $nameRules,
            'engine' => ['required', Rule::enum(\App\Enums\DatabaseEngine::class)],
            'version' => ['required', 'string', 'max:16'],
            'root_password' => $passwordRules,
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

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $server = $this->route('server');
            $requestedEngine = DatabaseEngine::tryFrom($this->input('engine'));

            if (! $server || ! $requestedEngine) {
                return;
            }

            $databaseConfig = app(DatabaseConfigurationService::class);

            // Check if server already has a database in this category
            if ($databaseConfig->hasExistingDatabaseInCategory($server, $requestedEngine)) {
                $category = $databaseConfig->isDatabaseCategory($requestedEngine)
                    ? 'database'
                    : 'cache/queue service';

                $validator->errors()->add(
                    'engine',
                    "This server already has a {$category} installed. Please uninstall the existing {$category} before installing a new one."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Database name is required.',
            'name.regex' => 'Database name can only contain letters, numbers, hyphens, and underscores (no spaces).',
            'engine.required' => 'Please select a database engine.',
            'version.required' => 'Please select a database version.',
            'root_password.required' => 'Root password is required.',
            'root_password.min' => 'Root password must be at least 8 characters.',
            'port.min' => 'Port must be at least 1.',
            'port.max' => 'Port cannot exceed 65535.',
            'port.unique' => 'This port is already in use by another database on this server.',
        ];
    }
}
