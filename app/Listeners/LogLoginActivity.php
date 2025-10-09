<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LogLoginActivity
{
    public function handle(Login $event): void
    {
        activity()
            ->causedBy($event->user)
            ->event('auth.login')
            ->withProperties([
                'ip' => request()->ip(),
                'user_id' => $event->user->id,
                'email' => $event->user->email ?? null,
            ])
            ->log('User logged in');
    }
}
