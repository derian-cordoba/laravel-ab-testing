<?php

declare(strict_types=1);

namespace ABTests\Blade;

use ABTests\Contracts\Bucketable;
use ABTests\Experiments;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Values\GenericUnit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Throwable;

/**
 * Static helpers called by the compiled output of @abVariant / @featureEnabled
 * and their counterparts. Resolved at render time, never at compile time.
 *
 * Unit resolution order (same as ResolveExperimentMiddleware):
 *  1. Auth::user() if it implements Bucketable.
 *  2. A GenericUnit keyed by the session ID (covers guests and APIs).
 */
final class BladeDirectiveHelpers
{
    /**
     * Returns true if the current unit is assigned to $variantKey in the
     * given experiment. Returns false when the experiment does not exist,
     * the unit is not eligible, or variant resolution fails for any reason.
     */
    public static function isVariant(string $experimentKey, string $variantKey): bool
    {
        try {
            $unit    = self::currentUnit();
            $variant = Experiments::for($unit)->variantForKey($experimentKey);

            return $variant?->key() === $variantKey;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Returns true when the current unit is NOT on $variantKey.
     */
    public static function isNotVariant(string $experimentKey, string $variantKey): bool
    {
        return ! self::isVariant($experimentKey, $variantKey);
    }

    /**
     * Returns true if the feature flag is enabled and the current unit falls
     * within the configured rollout percentage. Uses the DB state only — does
     * not invoke the FeatureFlag::resolve() method (which requires a Context).
     * For full resolve()-based evaluation, use FeatureFlags::for($unit) directly.
     */
    public static function featureEnabled(string $flagKey): bool
    {
        try {
            $state = FeatureFlagStateModel::query()->firstWhere('key', $flagKey);

            if ($state === null || ! $state->is_enabled || $state->killed_at !== null) {
                return false;
            }

            if ($state->rollout_percentage >= 100) {
                return true;
            }

            if ($state->rollout_percentage <= 0) {
                return false;
            }

            // Deterministic rollout check using the same position logic as Context.
            $unit     = self::currentUnit();
            $position = self::position($flagKey, $unit);

            return $position < ($state->rollout_percentage / 100);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Inverse of featureEnabled.
     */
    public static function featureDisabled(string $flagKey): bool
    {
        return ! self::featureEnabled($flagKey);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private static function currentUnit(): Bucketable
    {
        $user = Auth::user();

        if ($user instanceof Bucketable) {
            return $user;
        }

        return new GenericUnit(Session::getId());
    }

    /**
     * Deterministic position in [0, 1) for a flag key + unit, using the same
     * SHA-256 strategy as Sha256BucketingStrategy.
     */
    private static function position(string $flagKey, Bucketable $unit): float
    {
        $hash = hash('sha256', $flagKey . ':' . $unit->bucketingKey());
        $int  = hexdec(substr($hash, 0, 8));

        return $int / 0xFFFFFFFF;
    }
}
