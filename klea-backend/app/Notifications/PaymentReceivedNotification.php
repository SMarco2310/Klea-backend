<?php

namespace App\Notifications;

use App\Models\Transactions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
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
            ->subject('Payment received: ' . $plan->name)
            ->greeting('Payment received')
            ->line('A subscriber just paid for the "' . $plan->name . '" plan.')
            ->line('**Amount:** ' . number_format((float) $this->transaction->amount, 2) . ' ' . $this->transaction->currency)
            ->line('**Subscriber:** ' . $subscriber->external_id . ' (' . $subscriber->phone_number . ')')
            ->line('**Plan:** ' . $plan->name)
            ->action('View subscription', rtrim(config('app.frontend_url'), '/') . '/subscriptions/' . $subscription->id)
            ->line('Their subscription is now active.');
    }
}
