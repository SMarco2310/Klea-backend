<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Features extends Model
{
    /** @use HasFactory<\Database\Factories\FeaturesFactory> */
    use HasFactory;

    protected $fillable= [
        'application_id',
        'code', // a code that the user will define to facilitate the integration in his app 
        'description',
    ];

    // Relational Functions

    public function application()
    {
        return $this->belongsTo(Applications::class);
    }

    public function plans()
    {
        return $this->belongsToMany(Plans::class,'feature_plan')
        ->withPivot('limit')
        ->withTimestamps();
    }
}
