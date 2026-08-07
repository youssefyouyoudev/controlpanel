<?php

namespace App\Http\Requests\Operations;

use App\Services\Operations\HealthCheckService;
use Illuminate\Foundation\Http\FormRequest;

class StoreHealthCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'expected_status' => ['nullable', 'integer', 'min:100', 'max:599'],
            'expected_text' => ['nullable', 'string', 'max:255'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:10'],
            'allow_internal' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        app(HealthCheckService::class)->assertSafeUrl((string) $this->input('url'), (bool) $this->input('allow_internal', false));
    }
}
