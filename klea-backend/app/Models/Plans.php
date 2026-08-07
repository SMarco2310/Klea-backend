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
        'yearly_discount_percent',
        'position',
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
        // Explicit FKs because both model classes are plural (Plans,
        // Features), which breaks Laravel's default singular-based pivot
        // column guess (it would otherwise look for plans_id/features_id
        // instead of plan_id/feature_id).
        return $this->belongsToMany(Features::class, 'feature_plan', 'plan_id', 'feature_id')
        ->withPivot('limit')
        ->withTimestamps();
    }

    // Query Helper

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
