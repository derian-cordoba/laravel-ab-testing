<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class CreateExperimentRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'layer' => ['nullable', 'string', 'max:255'],
            'traffic_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
