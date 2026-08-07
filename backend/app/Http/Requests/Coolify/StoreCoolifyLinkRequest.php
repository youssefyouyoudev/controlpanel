<?php

namespace App\Http\Requests\Coolify;

use App\Enums\CoolifyResourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoolifyLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'website_component_id' => ['nullable', 'integer'],
            'resource_type' => ['required', Rule::enum(CoolifyResourceType::class)],
            'coolify_uuid' => ['required', 'string', 'max:191'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
