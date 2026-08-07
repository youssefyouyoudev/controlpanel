<?php

namespace App\Http\Requests\Coolify;

use App\Enums\DeploymentTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resource_link_id' => ['required', 'integer'],
            'trigger' => ['sometimes', Rule::enum(DeploymentTrigger::class)],
            'branch' => ['nullable', 'string', 'max:120', 'not_regex:/[;&|`$<>]/'],
            'commit_sha' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'commit_message' => ['nullable', 'string', 'max:500'],
            'confirmed' => ['accepted'],
            'typed_website_name' => ['nullable', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
