<?php

declare(strict_types=1);

namespace ABTests\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the query parameters for the assignments exposure endpoint.
 *
 * GET /ab-testing/assignments?unit_type=user&unit_key=42
 *
 * Callers must send the configured vendor media type in their Accept header
 * (default: application/vnd.ab-testing.v1+json). Wildcards such as *\/* are
 * rejected. In production unrecognized requests receive 404 to avoid leaking
 * endpoint existence; in other environments 406 is returned with a description.
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
