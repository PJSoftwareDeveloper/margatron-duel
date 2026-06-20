<?php

return [
    'action_points' => [
        'regeneration_seconds' => (int) env('GAME_ACTION_POINT_REGENERATION_SECONDS', 60),
        'regeneration_limit' => (int) env(
            'GAME_ACTION_POINT_REGENERATION_LIMIT',
            env('GAME_ACTION_POINT_MAX', 20),
        ),
    ],

    'rest' => [
        'options' => [
            1 => [
                'duration_seconds' => (int) env('GAME_REST_ONE_MINUTE_SECONDS', 60),
                'action_points' => 2,
            ],
            5 => [
                'duration_seconds' => (int) env('GAME_REST_FIVE_MINUTES_SECONDS', 300),
                'action_points' => 12,
            ],
        ],
        'instant' => [
            'gold_price' => 500,
        ],
    ],
];
