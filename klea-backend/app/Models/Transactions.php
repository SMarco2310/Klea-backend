<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transactions extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionsFactory> */
    use HasFactory;

    protected $fillable=
    [
       'subscription_id',
       'amount',
       'currency',
       'provider_tx_id',
       'payment_method', //this could be FLooz, Mix_by_Yas or card
       'phone_number',
       'status',
       'error_message',
       'environment'
    ];

    // Relational Functions

    public function webhookLogs(){
        return $this->hasOne(WebhookLogs::class);
    }

    public function subscription(){
        return $this->belongsTo(Subscriptions::class);
    }
}
