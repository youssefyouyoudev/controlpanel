<?php

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteRestrictedConsoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'website_component_id' => ['nullable', 'integer'],
            'command_alias' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'command' => ['prohibited'],
            'cmd' => ['prohibited'],
            'args' => ['prohibited'],
            'executable' => ['prohibited'],
            'container_id' => ['prohibited'],
            'coolify_uuid' => ['prohibited'],
        ];
    }
}
