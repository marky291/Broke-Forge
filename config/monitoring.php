<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration values for the server monitoring system.
    | These values control metrics collection intervals, data retention, and
    | API rate limiting.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection Interval
    |--------------------------------------------------------------------------
    |
    | How often metrics should be collected from remote servers (in seconds).
    | Default: 300 seconds (5 minutes)
    |
    */
    'collection_interval' => env('MONITORING_COLLECTION_INTERVAL', 300),

    /*
    |--------------------------------------------------------------------------
    | Data Retention Period
    |--------------------------------------------------------------------------
    |
    | How long to retain historical metrics data (in days).
    | Older metrics are automatically cleaned up via scheduled task.
    | Default: 7 days (matches the maximum timeframe users can select)
    |
    */
    'retention_days' => env('MONITORING_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Metrics Polling Interval (Frontend)
    |--------------------------------------------------------------------------
    |
    | How often the frontend should poll for new metrics (in milliseconds).
    | Default: 30000 milliseconds (30 seconds)
    |
    */
    'frontend_polling_interval' => env('MONITORING_FRONTEND_POLLING_INTERVAL', 30000),

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of metrics submissions allowed per minute per IP.
    | This prevents abuse and excessive API calls.
    | Default: 60 requests per minute
    |
    */
    'rate_limit' => env('MONITORING_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Script Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) the metrics collection script should wait
    | for a response when submitting metrics to the API.
    | Default: 10 seconds
    |
    */
    'script_timeout' => env('MONITORING_SCRIPT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Token Length
    |--------------------------------------------------------------------------
    |
    | Length of the generated monitoring authentication token (in bytes).
    | Tokens are hex-encoded, so actual string length will be double this.
    | Default: 32 bytes (64 character hex string)
    |
    */
    'token_length' => 32,

];
