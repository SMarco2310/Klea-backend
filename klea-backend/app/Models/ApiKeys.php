<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKeys extends Model
{
    /** @use HasFactory<\Database\Factories\ApiKeysFactory> */
    use HasFactory;

    protected $fillable = 
    [
       'application_id',
       'name',
       'environment',
       'public_id',
       'secret_hash',
       'last_used_at',
       'revoked_at'
    ];

    // relational functions

    public function application()
    {
        return $this->belongsTo(Applications::class);
    }

    
    // query helpers

    public function scopeActive($query)
    {
        return $query->where('environment', 'live')->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }


    // type casting function 

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}

