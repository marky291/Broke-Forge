<?php

return [
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
