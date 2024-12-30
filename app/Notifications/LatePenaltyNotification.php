<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LatePenaltyNotification extends Notification
{
    use Queueable;
    public $message;
    public $session;


    /**
     * Create a new notification instance.
     */
    public function __construct($message,$session)
    {
        $this->message = $message;
        $this->session = $session;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'session' => $this->session,
            'message' => $this->message
        ];
    }
}
