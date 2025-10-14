<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportHeaderValidationFailedNotification extends Notification
{
    use Queueable;
    public $errorMessage;
    /**
     * Create a new notification instance.
     */
    public function __construct(string $errorMessage)
    {
         $this->errorMessage = $errorMessage;
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
     * Get the mail representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->errorMessage,
            'icon' => 'fas fa-exclamation-triangle', // Ikon error
            'url' => route('admin.queue.monitor'), // Arahkan ke halaman monitor
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
