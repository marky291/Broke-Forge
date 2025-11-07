<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment Log Directory
    |--------------------------------------------------------------------------
    |
    | This value determines the directory on the remote server where deployment
    | logs will be stored. The directory should be writable by the brokeforge
    | user. Logs are stored with a unique filename for each deployment.
    |
    */

    'log_directory' => env('DEPLOYMENT_LOG_DIRECTORY', '/home/brokeforge/logs/deployment'),

    /*
    |--------------------------------------------------------------------------
    | Stream Interval
    |--------------------------------------------------------------------------
    |
    | The interval (in milliseconds) at which the frontend polls the deployment
    | log stream endpoint for active deployments. Lower values provide more
    | real-time updates but increase server load.
    |
    */

    'stream_interval' => (int) env('DEPLOYMENT_STREAM_INTERVAL', 500),
];
