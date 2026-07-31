<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantInvitations extends Model
{
    /** @use HasFactory<\Database\Factories\TenantInvitationsFactory> */
    use HasFactory;
    protected $fillable=
    [
        'tenant_id',
        'email',
        'role',
        'token',
        'invited_by',
        'status',
        'expires_at',
        'accepted_at'
    ];

    // relational functions

    public function tenant()
    {
        return $this->belongsTo(Tenants::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class,'invited_by');
    }

    // query helpers

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    // Type Casting function 

    protected function casts(): array
    {
        return [
            'expires_at'=>'datetime',
            'accepted_at'=>'datetime'
        ];
    }
}
