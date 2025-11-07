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

        // Get the selected framework to check requirements
        $framework = \App\Models\AvailableFramework::find($this->input('available_framework_id'));

        $rules = [
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]+([a-z0-9-]*[a-z0-9])?)*$/i',
                Rule::unique('server_sites')->where('server_id', $serverId),
            ],
            'available_framework_id' => [
                'required',
                'integer',
                Rule::exists('available_frameworks', 'id'),
            ],
            'php_version' => [
                'nullable',
                'string',
                'in:7.4,8.0,8.1,8.2,8.3',
            ],
            'ssl' => [
                'required',
                'boolean',
            ],
            'git_repository' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/',
            ],
            'git_branch' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._\/-]+$/',
            ],
        ];

        // Conditional validation based on framework requirements
        if ($framework) {
            // PHP version is required for PHP-based frameworks (not static HTML)
            if ($framework->slug !== 'static-html') {
                $rules['php_version'][] = 'required';
            }

            // Database is required if framework requires it
            if ($framework->requiresDatabase()) {
                $rules['database_id'] = [
                    'required',
                    'integer',
                    Rule::exists('server_databases', 'id')->where('server_id', $serverId)->where('status', 'active'),
                ];
            }

            // Node.js is required if framework requires it
            if ($framework->requiresNodejs()) {
                $rules['node_id'] = [
                    'required',
                    'integer',
                    Rule::exists('server_nodes', 'id')->where('server_id', $serverId)->where('status', 'active'),
                ];
            }
        }

        return $rules;
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.required' => 'The site name is required.',
            'domain.regex' => 'Please enter a valid name (e.g., example.com or my-project). Use only letters, numbers, hyphens, and dots.',
            'domain.unique' => 'This site name is already configured on this server.',
            'available_framework_id.required' => 'Please select a framework.',
            'available_framework_id.exists' => 'The selected framework is invalid.',
            'php_version.required' => 'Please select a PHP version.',
            'php_version.in' => 'Please select a valid PHP version.',
            'ssl.required' => 'Please specify whether to enable SSL.',
            'git_repository.required' => 'Git repository is required.',
            'git_repository.regex' => 'Repository must be in owner/repo format (e.g., owner/project).',
            'git_branch.required' => 'Branch name is required.',
            'git_branch.regex' => 'Branch name contains invalid characters.',
            'database_id.required' => 'Please select a database. This framework requires a database.',
            'database_id.exists' => 'The selected database is invalid or not active.',
            'node_id.required' => 'Please select a Node.js version. This framework requires Node.js.',
            'node_id.exists' => 'The selected Node.js version is invalid or not active.',
        ];
    }
}
