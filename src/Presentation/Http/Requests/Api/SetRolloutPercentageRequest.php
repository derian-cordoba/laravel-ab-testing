<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class SetRolloutPercentageRequest extends FormRequest
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
            'rollout_percentage' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
