<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Resources\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Serialization DTO for one unit's assignment map. Used by the API resource so
 * the controller remains thin and the JSON:API attributes stay explicit.
 */
final readonly class AssignmentsData implements Arrayable
{
    /**
     * @param  array<string, string>  $assignments
     */
    public function __construct(
        public string $unitType,
        public string $unitKey,
        public array $assignments,
    ) {
        //
    }

    /**
     * @return array{unit_type: string, unit_key: string, assignments: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'unit_type' => $this->unitType,
            'unit_key' => $this->unitKey,
            'assignments' => $this->assignments,
        ];
    }
}
