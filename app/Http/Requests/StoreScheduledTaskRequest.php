<?php

namespace App\Http\Requests;

use App\Enums\ScheduleFrequency;
use App\Packages\Services\Sites\Command\Rules\SafeCommand;
use App\Packages\Services\Sites\Command\Rules\ValidCronExpression;
use App\Packages\Services\Sites\Command\Rules\ValidPhpCommand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Ensure scheduler is active before allowing task creation
        $server = $this->route('server');

        return $server && $server->schedulerIsActive();
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
            'command' => ['required', 'string', 'max:1000', new SafeCommand, new ValidPhpCommand($server)],
            'frequency' => ['required', Rule::enum(ScheduleFrequency::class)],
            'cron_expression' => ['nullable', 'string', 'max:255', 'required_if:frequency,custom', new ValidCronExpression],
            'send_notifications' => ['boolean'],
            'timeout' => ['integer', 'min:1', 'max:'.config('scheduler.max_timeout')],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Check max tasks per server limit
        $server = $this->route('server');
        $maxTasks = config('scheduler.max_tasks_per_server', 50);

        if ($server && $server->scheduledTasks()->count() >= $maxTasks) {
            abort(422, "Maximum number of scheduled tasks ({$maxTasks}) reached for this server.");
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'task name',
            'command' => 'command',
            'frequency' => 'schedule frequency',
            'cron_expression' => 'custom cron expression',
            'send_notifications' => 'notification setting',
            'timeout' => 'timeout',
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
            'cron_expression.required_if' => 'A cron expression is required when using custom frequency',
            'timeout.max' => 'Timeout cannot exceed '.config('scheduler.max_timeout').' seconds',
        ];
    }
}
