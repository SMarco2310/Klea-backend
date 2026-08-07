<?php

namespace App\Notifications;

use App\Models\Transactions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Transactions $transaction)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->transaction->subscription;
        $subscriber = $subscription->subscriber;
        $plan = $subscription->plan;

        return (new MailMessage)
            ->subject('Payment failed: ' . $plan->name)
            ->greeting('Payment failed')
            ->line('A subscriber\'s payment for the "' . $plan->name . '" plan did not go through.')
            ->line('**Amount:** ' . number_format((float) $this->transaction->amount, 2) . ' ' . $this->transaction->currency)
            ->line('**Subscriber:** ' . $subscriber->external_id . ' (' . $subscriber->phone_number . ')')
            ->line('**Plan:** ' . $plan->name)
            ->action('View transaction', rtrim(config('app.frontend_url'), '/') . '/transactions/' . $this->transaction->id)
            ->line('No action is required — the subscriber can retry the payment from their app.');
    }
}
