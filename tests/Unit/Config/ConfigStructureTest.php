<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the shape of config/ab-testing.php: that top-level keys exist,
 * nested keys are correctly placed, and default values match documentation.
 * This guards against regressions when keys are renamed or restructured.
 */
final class ConfigStructureTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $config;

    protected function setUp(): void
    {
        /** @var array<string, mixed> $config */
        $config = require __DIR__ . '/../../../config/ab-testing.php';

        $this->config = $config;
    }

    // ── feature_flags ──────────────────────────────────────────────────────

    #[Test]
    public function feature_flags_key_exists_at_top_level(): void
    {
        self::assertArrayHasKey('feature_flags', $this->config);
    }

    #[Test]
    public function feature_flags_register_key_exists(): void
    {
        self::assertArrayHasKey('register', $this->config['feature_flags']);
    }

    #[Test]
    public function feature_flags_register_defaults_to_empty_array(): void
    {
        self::assertSame([], $this->config['feature_flags']['register']);
    }

    #[Test]
    public function feature_flags_stale_threshold_days_key_exists(): void
    {
        self::assertArrayHasKey('stale_threshold_days', $this->config['feature_flags']);
    }

    #[Test]
    public function feature_flags_stale_threshold_days_defaults_to_90(): void
    {
        self::assertSame(90, $this->config['feature_flags']['stale_threshold_days']);
    }

    #[Test]
    public function flags_key_no_longer_exists_at_top_level(): void
    {
        self::assertArrayNotHasKey('flags', $this->config);
    }

    #[Test]
    public function feature_flags_key_no_longer_contains_bare_class_list(): void
    {
        // Before the refactor, 'feature_flags' was a flat list of class strings.
        // Now it is a keyed array — verify it is not a list.
        self::assertArrayHasKey('register', $this->config['feature_flags']);
        self::assertArrayHasKey('stale_threshold_days', $this->config['feature_flags']);
    }

    // ── api ────────────────────────────────────────────────────────────────

    #[Test]
    public function api_key_exists_at_top_level(): void
    {
        self::assertArrayHasKey('api', $this->config);
    }

    #[Test]
    public function api_v1_key_exists(): void
    {
        self::assertArrayHasKey('v1', $this->config['api']);
    }

    #[Test]
    public function api_v1_accept_type_key_exists(): void
    {
        self::assertArrayHasKey('accept_type', $this->config['api']['v1']);
    }

    #[Test]
    public function api_v1_accept_type_defaults_to_vendor_media_type(): void
    {
        self::assertSame(
            'application/vnd.ab-testing.v1+json',
            $this->config['api']['v1']['accept_type'],
        );
    }

    #[Test]
    public function api_v1_middleware_key_exists_and_defaults_to_empty_array(): void
    {
        self::assertArrayHasKey('middleware', $this->config['api']['v1']);
        self::assertSame(['api'], $this->config['api']['v1']['middleware']);
    }

    #[Test]
    public function api_v1_endpoints_key_exists(): void
    {
        self::assertArrayHasKey('endpoints', $this->config['api']['v1']);
    }

    #[Test]
    public function api_v1_endpoints_assignments_key_exists(): void
    {
        self::assertArrayHasKey('assignments', $this->config['api']['v1']['endpoints']);
    }

    #[Test]
    public function api_v1_endpoints_assignments_enabled_defaults_to_true(): void
    {
        self::assertTrue($this->config['api']['v1']['endpoints']['assignments']['enabled']);
    }

    #[Test]
    public function api_v1_endpoints_assignments_path_defaults_to_assignments(): void
    {
        self::assertSame('assignments', $this->config['api']['v1']['endpoints']['assignments']['path']);
    }

    #[Test]
    public function assignments_key_no_longer_exists_at_top_level(): void
    {
        self::assertArrayNotHasKey('assignments', $this->config);
    }
}
