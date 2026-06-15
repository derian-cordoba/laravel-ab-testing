<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class SetFlagConditionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>|array<string, mixed>>
     */
    public function rules(): array
    {
        return [
            'conditions'                    => ['required', 'array'],
            'conditions.*.attribute'        => ['required', 'string'],
            'conditions.*.operator'         => ['required', 'string'],
            'conditions.*.expected'         => ['required'],
            'conditions_logic'              => ['nullable', 'string', 'in:all,any'],
        ];
    }
}
