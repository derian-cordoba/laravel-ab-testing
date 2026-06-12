<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * Decides whether a unit has consented to tracking. When a ConsentResolver is
 * configured, the event sink will call hasConsented() before writing any
 * exposure, conversion, or metric event. Units that have not consented are
 * still assigned a variant (bucketing still runs) but their events are not
 * recorded, preserving privacy while maintaining a consistent UX.
 *
 * Implement this interface and set privacy.consent_resolver in config/ab-testing.php
 * to the fully-qualified class name.
 */
interface ConsentResolver
{
    /**
     * Return true if the unit identified by $unitType / $unitKey has given
     * consent for A/B testing event recording.
     */
    public function hasConsented(string $unitType, string $unitKey): bool;
}
