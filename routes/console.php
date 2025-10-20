<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule monitoring metrics purge daily
Schedule::command('monitoring:purge')
    ->daily()
    ->at('03:00')
    ->onOneServer();

// Schedule scheduler task runs cleanup daily
Schedule::command('scheduler:cleanup')
    ->daily()
    ->at('03:15')
    ->onOneServer();

// Schedule stuck Git installations cleanup every 5 minutes
Schedule::command('git:cleanup-stuck')
    ->everyFiveMinutes()
    ->onOneServer();

// Evaluate server monitors every minute
Schedule::command('monitors:evaluate')
    ->everyMinute()
    ->onOneServer();
