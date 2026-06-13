<?php

declare(strict_types=1);

namespace ABTests\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query parameters for the assignments hydration endpoint.
 */
final class AssignmentsRequest extends FormRequest
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
            'unit_type' => ['required', 'string'],
            'unit_key'  => ['required', 'string'],
        ];
    }
}
