<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature\Api;

use ABTests\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for EnforceAcceptHeaderMiddleware.
 *
 * Verifies that every API endpoint rejects requests missing the configured
 * vendor Accept header and accepts requests that carry it correctly.
 *
 * Rejection cases pass an explicit Accept header as the $headers argument to
 * getJson/postJson, which overrides the vendor default injected by
 * FeatureTestCase::json().
 */
final class EnforceAcceptHeaderMiddlewareTest extends FeatureTestCase
{
    #[Test]
    public function request_with_wrong_accept_header_is_rejected(): void
    {
        // Non-production: 406 Not Acceptable.
        $this->getJson('/api/ab-testing/experiments', ['Accept' => 'application/json'])
            ->assertStatus(406);
    }

    #[Test]
    public function request_with_wildcard_accept_header_is_rejected(): void
    {
        $this->getJson('/api/ab-testing/experiments', ['Accept' => '*/*'])
            ->assertStatus(406);
    }

    #[Test]
    public function request_with_correct_accept_header_is_allowed(): void
    {
        $acceptType = config('ab-testing.api.v1.accept_type', 'application/vnd.ab-testing.v1+json');

        $this->getJson('/api/ab-testing/experiments', ['Accept' => $acceptType])
            ->assertStatus(200);
    }

    #[Test]
    public function enforcement_applies_to_post_endpoints_too(): void
    {
        $this->postJson('/api/ab-testing/experiments', ['key' => 'test-experiment'], ['Accept' => 'text/html'])
            ->assertStatus(406);
    }
}
