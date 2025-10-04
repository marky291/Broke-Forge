<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by ValidateSchedulerToken middleware,
     * so we always return true here.
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
            'task_id' => ['required', 'integer', 'exists:server_scheduled_tasks,id'],
            'started_at' => ['required', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'exit_code' => ['nullable', 'integer'],
            'output' => ['nullable', 'string'],
            'error_output' => ['nullable', 'string'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'task_id' => 'task ID',
            'started_at' => 'start time',
            'completed_at' => 'completion time',
            'exit_code' => 'exit code',
            'output' => 'output',
            'error_output' => 'error output',
            'duration_ms' => 'duration',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'completed_at.after_or_equal' => 'Completion time must be after or equal to start time',
            'task_id.exists' => 'The specified task does not exist',
        ];
    }
}
