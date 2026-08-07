<?php

namespace App\Http\Requests\Coolify;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResourceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['sometimes', Rule::in(['start', 'stop', 'restart'])],
            'confirmed' => ['sometimes', 'boolean'],
            'coolify_uuid' => ['prohibited'],
            'container_id' => ['prohibited'],
            'docker_container_id' => ['prohibited'],
        ];
    }
}
