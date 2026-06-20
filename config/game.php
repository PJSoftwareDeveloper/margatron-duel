<?php

return [
    'action_points' => [
        'regeneration_seconds' => (int) env('GAME_ACTION_POINT_REGENERATION_SECONDS', 60),
        'max' => (int) env('GAME_ACTION_POINT_MAX', 20),
    ],
];
