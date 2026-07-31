<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLogs extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookLogsFactory> */
    use HasFactory;

    protected $fillable=[
        'provider_tx_id',
        'payload',
        'status_code',
        'processed_at'
    ];

    // Relational function

    public function transaction()
    {
        return $this->belongsTo(Transactions::class);
    }

    public function scopeFailed($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    // Type Casting Function

    protected function casts(): array
    {
        return [
            'payload' => 'object',
            'processed_at' => 'datetime',
        ];
    }
}
