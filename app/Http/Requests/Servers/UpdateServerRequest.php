<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $serverId = $this->route('server')?->id;

        return [
            'vanity_name' => ['required', 'string', 'max:100'],
            'public_ip' => ['required', 'ip'],
            'ssh_port' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('servers')
                    ->ignore($serverId)
                    ->where(fn ($q) => $q->where('public_ip', $this->input('public_ip'))),
            ],
            'private_ip' => ['nullable', 'ip'],
        ];
    }
}
