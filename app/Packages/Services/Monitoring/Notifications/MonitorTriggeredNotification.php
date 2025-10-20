<?php

namespace App\Packages\Services\Monitoring\Notifications;

use App\Models\Server;
use App\Models\ServerMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorTriggeredNotification extends Notification implements ShouldQueue
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
            ->error()
            ->subject("Monitor Alert: {$this->monitor->name}")
            ->greeting("Alert: {$this->monitor->name}")
            ->line('Your server monitor has been triggered.')
            ->line("**Server:** {$this->server->vanity_name}")
            ->line("**Monitor:** {$this->monitor->name}")
            ->line("**Condition:** {$metricName} {$this->monitor->operator} {$this->monitor->threshold}% for {$this->monitor->duration_minutes} minute(s)")
            ->line("**Current Value:** {$this->currentValue}%")
            ->line("This condition has persisted for {$this->monitor->duration_minutes} minute(s).")
            ->action('View Server Monitoring', $url)
            ->line('This alert will not trigger again for '.$this->monitor->cooldown_minutes.' minute(s).');
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
