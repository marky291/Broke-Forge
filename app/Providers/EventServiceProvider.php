<?php

namespace App\Providers;

use App\Listeners\LogLoginActivity;
use App\Packages\Services\Monitoring\Listeners\SendMonitorAlertNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            LogLoginActivity::class,
        ],
    ];

    protected $subscribe = [
        SendMonitorAlertNotification::class,
    ];
}
