<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriptions extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionsFactory> */
    use HasFactory;

    protected $fillable=
    [
       'subscriber_id',
       'plan_id',
       'status',
       'starts_at', // I will set this one to the time the user check outs || I should wait for the validation from semoa??
       'expires_at',
       'cancelled_at',
       'environment'
    ];

    // Relational Functions

    public function subscriber()
    {
        return $this->belongsTo(Subscribers::class); 
    }

    public function transactions()
    {
        return $this->hasMany(Transactions::class);
    }


    public function plan()
    {
        return $this->belongsTo(Plans::class,'plan_id');
    }


    // Query helper 

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeLive($query)
    {
        return $query->where('environment', 'live');
    }

    public function isActive(): bool
    {
    return $this->status === 'active' && $this->expires_at?->isFuture();
    }

    public function isCancelled(): bool
    {
        return !is_null($this->cancelled_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    // Type Casting function

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}

