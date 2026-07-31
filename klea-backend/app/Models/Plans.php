<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plans extends Model
{
    /** @use HasFactory<\Database\Factories\PlansFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable= [
        'application_id',
        'name',
        'price',
        'currency',
        'duration_days',
        'grace_period_days',
        'is_active',
    ];

    // Relational Functions

    public function application(){
        return $this->belongsTo(Applications::class);
    }

    public function subscriptions(){
        return $this->hasMany(Subscriptions::class);
    }

    public function features()
    {
        return $this->belongsToMany(Features::class,'feature_plan')
        ->withPivot('limit')
        ->withTimestamps();
    }

    // Query Helper

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
