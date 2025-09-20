<?php

namespace App\Listeners;

use App\Models\Activity;
use Illuminate\Auth\Events\Login;

class LogLoginActivity
{
    public function handle(Login $event): void
    {
        Activity::create([
            'type' => 'auth.login',
            'description' => 'User logged in',
            'causer_id' => $event->user->id,
            'properties' => [
                'ip' => request()->ip(),
                'user_id' => $event->user->id,
                'email' => $event->user->email ?? null,
            ],
        ]);
    }
}
