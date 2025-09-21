<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallSiteGitRepositoryRequest extends FormRequest
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
        return [
            'provider' => ['required', 'string', Rule::in(['github'])],
            'repository' => [
                'required',
                'string',
                'max:255',
                'regex:/^(git@|ssh:\/\/|https:\/\/).+|[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/',
            ],
            'branch' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._\/-]+$/',
            ],
            'document_root' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!\s*$).+/',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->string('provider')->trim()->lower()->value(),
            'repository' => $this->string('repository')->trim()->value(),
            'branch' => $this->string('branch')->trim()->value(),
            'document_root' => $this->has('document_root')
                ? $this->string('document_root')->trim()->value()
                : null,
        ]);
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider.in' => 'Only GitHub repositories are supported at this time.',
            'repository.regex' => 'Enter the repository using owner/name or an SSH URL.',
            'branch.regex' => 'Branch names may contain letters, numbers, dashes, underscores, periods, or slashes only.',
            'document_root.regex' => 'Document root must be a valid path.',
        ];
    }
}
