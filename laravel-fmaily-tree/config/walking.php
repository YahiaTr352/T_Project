<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Daily Walking Goal (Steps)
    |--------------------------------------------------------------------------
    |
    | The default daily goal threshold. When a participant hits or exceeds
    | this number of steps on a given day, congratulatory Push and WhatsApp
    | notifications are dispatched with the actual steps walked.
    |
    */
    'daily_goal_steps' => (int) env('WALKING_DAILY_GOAL_STEPS', 10000),

    /*
    |--------------------------------------------------------------------------
    | Daily Step Ceiling & Anti-Anomaly Limits
    |--------------------------------------------------------------------------
    |
    | When enforce_daily_cap is false, the daily steps ceiling is open/unbounded
    | allowing ultra-endurance walkers and athletes to record any number of steps.
    |
    */
    'enforce_daily_cap'  => (bool) env('WALKING_ENFORCE_DAILY_CAP', false),
    'max_daily_steps'    => (int) env('WALKING_MAX_DAILY_STEPS', 120000),
    'max_steps_per_hour' => (int) env('WALKING_MAX_STEPS_PER_HOUR', 12000),
];
