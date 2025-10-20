<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerMonitorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $server = $this->route('server');

        return $server && $server->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'metric_type' => ['required', Rule::in(['cpu', 'memory', 'storage'])],
            'operator' => ['required', Rule::in(['>', '<', '>=', '<=', '=='])],
            'threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'notification_emails' => ['required', 'array', 'min:1', 'max:10'],
            'notification_emails.*' => ['required', 'email:rfc,dns'],
            'enabled' => ['sometimes', 'boolean'],
            'cooldown_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
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
            'name' => 'monitor name',
            'metric_type' => 'metric type',
            'operator' => 'comparison operator',
            'threshold' => 'threshold value',
            'duration_minutes' => 'duration',
            'notification_emails' => 'notification emails',
            'notification_emails.*' => 'email address',
            'cooldown_minutes' => 'cooldown period',
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
            'threshold.max' => 'Threshold cannot exceed 100%',
            'duration_minutes.max' => 'Duration cannot exceed 24 hours (1440 minutes)',
            'notification_emails.min' => 'At least one email address is required',
            'notification_emails.max' => 'Maximum of 10 email addresses allowed',
            'cooldown_minutes.max' => 'Cooldown period cannot exceed 24 hours (1440 minutes)',
        ];
    }
}
