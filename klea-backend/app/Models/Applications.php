<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applications extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationsFactory> */
    use HasFactory;

    protected $fillable= [
        'tenant_id',
        'name',
        'slug', // url identifier used instead of id in the url
        'status',
        'webhook_url',
        'webhook_secret'
    ];

    // Relational function 

    public function tenant()
    {
        return $this->belongsTo(Tenants::class);
    }

    public function plans()
    {
        return $this->hasMany(Plans::class);
    }

    public function features()
    {
        return $this->hasMany(Features::class);
    }
    
    public function api_keys()
    {
        return $this->hasMany(ApiKeys::class);
    }

    // Query helper

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
