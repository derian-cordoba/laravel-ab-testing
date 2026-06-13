<?php

declare(strict_types=1);

/**
 * Minimal A/B testing config for the Feature test application.
 * Uses no middleware groups (the test app has no 'api' group defined)
 * and leaves the manage gate undefined so RequiresApiAccess always allows.
 */
return [
    'api' => [
        'v1' => [
            'accept_type' => 'application/vnd.ab-testing.v1+json',
            'middleware' => [],
            'manage_gate' => 'manageAbTestingApi',
            'endpoints' => [
                'assignments' => [
                    'enabled' => true,
                    'path' => 'assignments',
                ],
                'experiments' => [
                    'enabled' => true,
                    'prefix'  => 'api/v1/ab-testing',
                ],
            ],
        ],
    ],
];
