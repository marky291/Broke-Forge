<?php

namespace App\Packages\Services\Monitoring\Listeners;

use App\Packages\Services\Monitoring\Events\MonitorRecoveredEvent;
use App\Packages\Services\Monitoring\Events\MonitorTriggeredEvent;
use App\Packages\Services\Monitoring\Notifications\MonitorRecoveredNotification;
use App\Packages\Services\Monitoring\Notifications\MonitorTriggeredNotification;
use Illuminate\Support\Facades\Notification;

class SendMonitorAlertNotification
{
    public function handleTriggered(MonitorTriggeredEvent $event): void
    {
        $emails = $event->monitor->notification_emails;

        if (empty($emails)) {
            return;
        }

        Notification::route('mail', $emails)
            ->notify(new MonitorTriggeredNotification(
                $event->monitor,
                $event->server,
                $event->currentValue
            ));
    }

    public function handleRecovered(MonitorRecoveredEvent $event): void
    {
        $emails = $event->monitor->notification_emails;

        if (empty($emails)) {
            return;
        }

        Notification::route('mail', $emails)
            ->notify(new MonitorRecoveredNotification(
                $event->monitor,
                $event->server,
                $event->currentValue
            ));
    }

    public function subscribe(object $events): array
    {
        return [
            MonitorTriggeredEvent::class => 'handleTriggered',
            MonitorRecoveredEvent::class => 'handleRecovered',
        ];
    }
}
