<?php

namespace App\Http\Requests\Servers;

use App\Models\Server;
use App\Models\ServerFirewallRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FirewallRuleRequest extends FormRequest
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
        /** @var Server $server */
        $server = $this->route('server');

        return [
            'name' => ['required', 'string', 'max:255'],
            'port' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($server) {
                    // Skip validation if no port provided
                    if (! $value) {
                        return;
                    }

                    // Validate port format (single port or range)
                    if (! preg_match('/^\d{1,5}(-\d{1,5})?$/', $value)) {
                        $fail('The port must be a valid port number (1-65535) or range (e.g., 3000-3005).');

                        return;
                    }

                    // Validate port number ranges
                    if (str_contains($value, '-')) {
                        [$start, $end] = explode('-', $value);

                        if ((int) $start >= (int) $end) {
                            $fail('Port range start must be less than end.');

                            return;
                        }

                        if ((int) $start < 1 || (int) $end > 65535) {
                            $fail('Port numbers must be between 1 and 65535.');

                            return;
                        }
                    } else {
                        if ((int) $value < 1 || (int) $value > 65535) {
                            $fail('Port number must be between 1 and 65535.');

                            return;
                        }
                    }

                    // Check for duplicate port within the same server's firewall
                    if ($server->firewall) {
                        $existingRule = ServerFirewallRule::query()
                            ->where('server_firewall_id', $server->firewall->id)
                            ->where('port', $value)
                            ->exists();

                        if ($existingRule) {
                            $fail('A firewall rule for this port already exists on this server.');
                        }
                    }
                },
            ],
            'from_ip_address' => ['nullable', 'string', 'ip'],
            'rule_type' => ['nullable', 'string', Rule::in(['allow', 'deny'])],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Rule name is required.',
            'name.max' => 'Rule name must not exceed 255 characters.',
            'from_ip_address.ip' => 'From IP address must be a valid IP address.',
            'rule_type.in' => 'Rule type must be either "allow" or "deny".',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from string inputs
        $this->merge(array_filter([
            'name' => $this->input('name') ? trim($this->input('name')) : null,
            'port' => $this->input('port') ? trim($this->input('port')) : null,
            'from_ip_address' => $this->input('from_ip_address') ? trim($this->input('from_ip_address')) : null,
            'rule_type' => $this->input('rule_type'),
        ]));
    }
}
