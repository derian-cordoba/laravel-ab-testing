<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Http\Resources;

use ABTests\Http\Resources\AssignmentsResource;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AssignmentsResourceTest extends TestCase
{
    private function makeRequest(string $unitType, string $unitKey): Request
    {
        return Request::create(
            '/ab-testing/assignments',
            'GET',
            ['unit_type' => $unitType, 'unit_key' => $unitKey],
        );
    }

    #[Test]
    public function to_array_includes_unit_type_from_request(): void
    {
        $resource = new AssignmentsResource([]);
        $request  = $this->makeRequest('user', '42');

        $result = $resource->toArray($request);

        self::assertSame('user', $result['unit_type']);
    }

    #[Test]
    public function to_array_includes_unit_key_from_request(): void
    {
        $resource = new AssignmentsResource([]);
        $request  = $this->makeRequest('tenant', '99');

        $result = $resource->toArray($request);

        self::assertSame('99', $result['unit_key']);
    }

    #[Test]
    public function to_array_includes_assignments_from_resource(): void
    {
        $assignments = [
            'checkout-button-color' => 'green',
            'pricing-page-layout'   => 'control',
        ];

        $resource = new AssignmentsResource($assignments);
        $request  = $this->makeRequest('user', '1');

        $result = $resource->toArray($request);

        self::assertSame($assignments, $result['assignments']);
    }

    #[Test]
    public function to_array_produces_empty_assignments_when_unit_has_no_assignments(): void
    {
        $resource = new AssignmentsResource([]);
        $request  = $this->makeRequest('user', '1');

        $result = $resource->toArray($request);

        self::assertSame([], $result['assignments']);
    }

    #[Test]
    public function to_array_shape_contains_exactly_three_keys(): void
    {
        $resource = new AssignmentsResource(['some-experiment' => 'treatment']);
        $request  = $this->makeRequest('user', '7');

        $result = $resource->toArray($request);

        self::assertSame(['unit_type', 'unit_key', 'assignments'], array_keys($result));
    }

    #[Test]
    public function assignments_are_passed_through_unmodified(): void
    {
        $assignments = [
            'exp-a' => 'control',
            'exp-b' => 'variant-1',
            'exp-c' => 'variant-2',
        ];

        $resource = new AssignmentsResource($assignments);
        $result   = $resource->toArray($this->makeRequest('user', '1'));

        self::assertCount(3, $result['assignments']);
        self::assertSame('control',   $result['assignments']['exp-a']);
        self::assertSame('variant-1', $result['assignments']['exp-b']);
        self::assertSame('variant-2', $result['assignments']['exp-c']);
    }
}
