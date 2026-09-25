<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiskAlertNotification extends Notification
{
    use Queueable;

    public function __construct(protected Alert $alert) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerta agroclimática: '.$this->alert->risk_type)
            ->line('Riesgo detectado: '.$this->alert->risk_type)
            ->line($this->alert->message)
            ->action('Ver dashboard', url('/'))
            ->line('Fuente: '.$this->alert->source);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'risk_type' => $this->alert->risk_type,
            'message' => $this->alert->message,
        ];
    }
}
