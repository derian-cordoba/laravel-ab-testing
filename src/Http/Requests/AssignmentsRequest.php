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

    protected function prepareForValidation(): void
    {
        /** @var string $requiredType */
        $requiredType = config('ab-testing.api.v1.accept_type', 'application/vnd.ab-testing.v1+json');

        $accepted = $this->getAcceptableContentTypes();

        if (! in_array($requiredType, $accepted, strict: true)) {
            $isProduction = app()->isProduction();

            throw new HttpResponseException(
                response()->json(
                    $isProduction ? [] : ['error' => "This endpoint requires Accept: $requiredType."],
                    $isProduction ? Response::HTTP_NOT_FOUND : Response::HTTP_NOT_ACCEPTABLE,
                ),
            );
        }
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
