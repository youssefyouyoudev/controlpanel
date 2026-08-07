<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWebsiteComponentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:160'],
            'type' => ['required', Rule::in(['laravel', 'nextjs', 'vite', 'node', 'static', 'database', 'worker', 'custom'])],
            'relative_working_directory' => ['nullable', 'string', 'max:2048'],
            'runtime' => ['nullable', 'string', 'max:80'],
            'process_manager' => ['nullable', Rule::in(['pm2'])],
            'process_name' => ['nullable', 'string', 'max:160'],
            'build_command_key' => ['nullable', 'string', 'max:160'],
            'start_command_key' => ['nullable', 'string', 'max:160'],
            'health_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['nullable', Rule::in(['healthy', 'degraded', 'offline', 'unknown', 'maintenance'])],
            'configuration' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $path = str_replace('\\', '/', (string) $this->input('relative_working_directory', ''));
                if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1 || str_contains($path, '..') || str_contains($path, "\0")) {
                    $validator->errors()->add('relative_working_directory', 'Working directory must be relative to an approved root.');
                }
            },
        ];
    }
}
