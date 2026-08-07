<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'website_component_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:160'],
            'type' => ['required', Rule::in(['laravel', 'pm2', 'nginx_access', 'nginx_error', 'php_fpm', 'cloudflared', 'docker', 'coolify', 'action', 'backup'])],
            'absolute_path' => ['nullable', 'string', 'max:2048'],
            'download_enabled' => ['boolean'],
            'is_sensitive' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
