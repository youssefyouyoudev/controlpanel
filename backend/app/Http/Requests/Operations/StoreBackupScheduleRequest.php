<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackupScheduleRequest extends FormRequest
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
            'backup_type' => ['required', Rule::in(['files', 'database', 'configuration', 'full', 'pre_deployment', 'pre_migration', 'manual'])],
            'schedule' => ['required', Rule::in(['daily', 'weekly'])],
            'retention_count' => ['required', 'integer', 'min:1', 'max:30'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_enabled' => ['boolean'],
        ];
    }
}
