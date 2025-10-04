<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServerMetricsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by ValidateMonitoringToken middleware,
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
            'cpu_usage' => ['required', 'numeric', 'min:0', 'max:100'],
            'memory_total_mb' => ['required', 'integer', 'min:0'],
            'memory_used_mb' => ['required', 'integer', 'min:0'],
            'memory_usage_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'storage_total_gb' => ['required', 'integer', 'min:0'],
            'storage_used_gb' => ['required', 'integer', 'min:0'],
            'storage_usage_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'collected_at' => ['required', 'date'],
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
            'cpu_usage' => 'CPU usage',
            'memory_total_mb' => 'total memory',
            'memory_used_mb' => 'used memory',
            'memory_usage_percentage' => 'memory usage percentage',
            'storage_total_gb' => 'total storage',
            'storage_used_gb' => 'used storage',
            'storage_usage_percentage' => 'storage usage percentage',
            'collected_at' => 'collection timestamp',
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
            'cpu_usage.max' => 'CPU usage cannot exceed 100%',
            'memory_usage_percentage.max' => 'Memory usage percentage cannot exceed 100%',
            'storage_usage_percentage.max' => 'Storage usage percentage cannot exceed 100%',
        ];
    }
}
