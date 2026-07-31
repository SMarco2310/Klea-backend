<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscribers extends Model
{
    /** @use HasFactory<\Database\Factories\SubscribersFactory> */
    use HasFactory;

    protected $fillable=[
        'tenant_id',
        'external_id',
        'phone_number',
        'email',
        'environment',
    ];

    // Relational function

    public function subscriptions()
    {
        return $this->hasMany(Subscriptions::class);
    }

    public function tenant(){
        return $this->belongsTo(Tenants::class);
    }

    // Query helper 

    public function scopeLive($query)
    {
        return $query->where('environment', 'live');
    }
}
