<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature\Api;

use ABTests\Infrastructure\Database\Models\AssignmentModel;
use ABTests\Tests\Feature\FeatureTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;

final class AssignmentsControllerTest extends FeatureTestCase
{
    #[Test]
    public function show_returns_json_api_resource_for_unit_assignments(): void
    {
        AssignmentModel::query()->create([
            'experiment_key' => 'checkout-button-color',
            'unit_type' => 'user',
            'unit_key' => '42',
            'variant_key' => 'green',
            'layer' => 'checkout',
            'assigned_at' => new DateTimeImmutable(),
        ]);

        AssignmentModel::query()->create([
            'experiment_key' => 'pricing-page-layout',
            'unit_type' => 'user',
            'unit_key' => '42',
            'variant_key' => 'control',
            'layer' => 'pricing',
            'assigned_at' => new DateTimeImmutable(),
        ]);

        $response = $this->getJson('/api/ab-testing/assignments?unit_type=user&unit_key=42');

        $response->assertStatus(200);
        $response->assertJsonPath('data.type', 'assignments');
        $response->assertJsonPath('data.id', 'user:42');
        $response->assertJsonPath('data.attributes.unit_type', 'user');
        $response->assertJsonPath('data.attributes.unit_key', '42');
        $response->assertJsonPath('data.attributes.assignments.checkout-button-color', 'green');
        $response->assertJsonPath('data.attributes.assignments.pricing-page-layout', 'control');
    }

    #[Test]
    public function show_returns_empty_assignment_map_when_unit_has_no_assignments(): void
    {
        $response = $this->getJson('/api/ab-testing/assignments?unit_type=user&unit_key=404');

        $response->assertStatus(200);
        $response->assertJsonPath('data.type', 'assignments');
        $response->assertJsonPath('data.id', 'user:404');
        $response->assertJsonPath('data.attributes.assignments', []);
    }

    #[Test]
    public function show_scopes_assignments_to_the_requested_unit(): void
    {
        AssignmentModel::query()->create([
            'experiment_key' => 'checkout-button-color',
            'unit_type' => 'user',
            'unit_key' => '42',
            'variant_key' => 'green',
            'layer' => 'checkout',
            'assigned_at' => new DateTimeImmutable(),
        ]);

        AssignmentModel::query()->create([
            'experiment_key' => 'checkout-button-color',
            'unit_type' => 'user',
            'unit_key' => '77',
            'variant_key' => 'control',
            'layer' => 'checkout',
            'assigned_at' => new DateTimeImmutable(),
        ]);

        $response = $this->getJson('/api/ab-testing/assignments?unit_type=user&unit_key=42');

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.assignments.checkout-button-color', 'green');
        $response->assertJsonMissing(['checkout-button-color' => 'control']);
    }

    #[Test]
    public function show_returns_422_when_unit_type_is_missing(): void
    {
        $response = $this->getJson('/api/ab-testing/assignments?unit_key=42');

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'The unit type field is required.');
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/unit_type');
    }

    #[Test]
    public function show_returns_422_when_unit_key_is_missing(): void
    {
        $response = $this->getJson('/api/ab-testing/assignments?unit_type=user');

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'The unit key field is required.');
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/unit_key');
    }
}
