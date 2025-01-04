<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompleteSessionNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public $session;
    public $message;

    /**
     * Create a new notification instance.
     */
    public function __construct($session, $message)
    {
        $this->session = $session;
        $this->message = $message;
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
