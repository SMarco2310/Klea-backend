<?php

namespace App\Notifications;

use App\Models\TenantInvitations;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(protected TenantInvitations $invitation)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = rtrim(config('app.frontend_url'), '/') . '/invitations/' . $this->invitation->token;

        return (new MailMessage)
            ->subject('You have been invited to join ' . $this->invitation->tenant->name . ' on Klea')
            ->line('You have been invited to join "' . $this->invitation->tenant->name . '" as a ' . $this->invitation->role . '.')
            ->action('View invitation', $acceptUrl)
            ->line('This invitation expires on ' . $this->invitation->expires_at->format('M j, Y H:i') . '.');
    }
}
