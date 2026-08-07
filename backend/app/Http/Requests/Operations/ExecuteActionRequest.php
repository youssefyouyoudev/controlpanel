<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExecuteActionRequest extends FormRequest
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
            'website_component_id' => ['nullable', 'integer'],
            'options' => ['nullable', 'array'],
            'options.confirmed' => ['nullable', 'boolean'],
            'options.typed_website_name' => ['nullable', 'string', 'max:255'],
            'options.password' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['command', 'executable', 'working_directory', 'arguments', 'process_name'] as $forbidden) {
                    if ($this->has($forbidden) || $this->has("options.{$forbidden}")) {
                        $validator->errors()->add($forbidden, 'Raw commands, executables, paths and process names are not accepted.');
                    }
                }
            },
        ];
    }
}
