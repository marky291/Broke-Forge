<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduler Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration values for the server task scheduler.
    | These values control task retention, API rate limiting, and default timeouts.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Task Run Retention Period
    |--------------------------------------------------------------------------
    |
    | How long to retain historical task run data (in days).
    | Older task runs are automatically cleaned up via scheduled task.
    | Default: 90 days
    |
    */
    'retention_days' => env('SCHEDULER_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of heartbeat submissions allowed per minute per IP.
    | This prevents abuse and excessive API calls.
    | Default: 120 requests per minute
    |
    */
    'rate_limit' => env('SCHEDULER_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Token Length
    |--------------------------------------------------------------------------
    |
    | Length of the generated scheduler authentication token (in bytes).
    | Tokens are hex-encoded, so actual string length will be double this.
    | Default: 32 bytes (64 character hex string)
    |
    */
    'token_length' => 32,

    /*
    |--------------------------------------------------------------------------
    | Default Task Timeout
    |--------------------------------------------------------------------------
    |
    | Default maximum execution time for scheduled tasks (in seconds).
    | This can be overridden per-task.
    | Default: 300 seconds (5 minutes)
    |
    */
    'default_timeout' => env('SCHEDULER_DEFAULT_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Maximum Task Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum allowed timeout value for scheduled tasks (in seconds).
    | This prevents tasks from running indefinitely.
    | Default: 3600 seconds (1 hour)
    |
    */
    'max_timeout' => env('SCHEDULER_MAX_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Script Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) the task wrapper script should wait
    | for a response when submitting heartbeats to the API.
    | Default: 10 seconds
    |
    */
    'script_timeout' => env('SCHEDULER_SCRIPT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Maximum Tasks Per Server
    |--------------------------------------------------------------------------
    |
    | Maximum number of scheduled tasks allowed per server.
    | This prevents resource exhaustion and DoS attacks.
    | Default: 50 tasks
    |
    */
    'max_tasks_per_server' => env('SCHEDULER_MAX_TASKS_PER_SERVER', 50),

];
