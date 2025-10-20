<?php

namespace App\Packages\Services\Monitoring\Notifications;

use App\Models\Server;
use App\Models\ServerMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorRecoveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ServerMonitor $monitor,
        public Server $server,
        public float $currentValue,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $metricName = ucfirst($this->monitor->metric_type);
        $url = route('servers.monitoring', ['server' => $this->server->id]);

        return (new MailMessage)
            ->success()
            ->subject("Monitor Resolved: {$this->monitor->name}")
            ->greeting("Resolved: {$this->monitor->name}")
            ->line('Your server monitor condition has returned to normal.')
            ->line("**Server:** {$this->server->vanity_name}")
            ->line("**Monitor:** {$this->monitor->name}")
            ->line("**Condition:** {$metricName} {$this->monitor->operator} {$this->monitor->threshold}%")
            ->line("**Current Value:** {$this->currentValue}%")
            ->line('The condition is no longer being met.')
            ->action('View Server Monitoring', $url);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'monitor_id' => $this->monitor->id,
            'server_id' => $this->server->id,
            'monitor_name' => $this->monitor->name,
            'metric_type' => $this->monitor->metric_type,
            'current_value' => $this->currentValue,
            'threshold' => $this->monitor->threshold,
        ];
    }
}
