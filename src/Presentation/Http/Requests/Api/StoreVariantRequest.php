<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class StoreVariantRequest extends FormRequest
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
            'key'        => ['required', 'string', 'max:255'],
            'weight'     => ['required', 'integer', 'min:0', 'max:100'],
            'is_control' => ['sometimes', 'boolean'],
        ];
    }
}
