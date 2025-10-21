<?php

namespace App\Http\Requests\Servers;

use App\Enums\DatabaseType;
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

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $server = $this->route('server');
            $requestedType = DatabaseType::tryFrom($this->input('type'));

            if (! $server || ! $requestedType) {
                return;
            }

            $databaseConfig = app(DatabaseConfigurationService::class);

            // Check if server already has a database in this category
            if ($databaseConfig->hasExistingDatabaseInCategory($server, $requestedType)) {
                $category = $databaseConfig->isDatabaseCategory($requestedType)
                    ? 'database'
                    : 'cache/queue service';

                $validator->errors()->add(
                    'type',
                    "This server already has a {$category} installed. Please uninstall the existing {$category} before installing a new one."
                );
            }
        });
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
