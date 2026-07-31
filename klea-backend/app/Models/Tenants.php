<?php

namespace App\Models;

use App\Models\Subscribers;
use App\Models\Applications;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenants extends Model
{
    /** @use HasFactory<\Database\Factories\TenantsFactory> */
    use HasFactory;

    protected $fillable= [
        'name',
        'slug', // url identifier used instead of id in the url
        'semoa_api_key',
        'role',
        'semoa_merchant_id',
        'status',
        'settings' // json field for storing the settings of the tenant
    ];

    // Relational Functions

    public function subscribers()
    {
        return $this->hasMany(Subscribers::class);
    }

    public function applications()
    {
        return $this->hasMany(Applications::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class,'tenant_user')
        ->withPivot('role')
        ->withTimestamps();
    }

    public function invitations()
    {
        return $this->hasMany(TenantInvitations::class);
    }

    // Query Helper

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Type casting function

    protected function casts(): array
    {
        return [
            'settings' => 'object', // json field for storing the settings of the tenant
        ];
    }
}
