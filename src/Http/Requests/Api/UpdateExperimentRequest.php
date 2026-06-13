<?php

declare(strict_types=1);

namespace ABTests\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateExperimentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name'               => ['nullable', 'string', 'max:255'],
            'layer'              => ['nullable', 'string', 'max:255'],
            'target_sample_size' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
