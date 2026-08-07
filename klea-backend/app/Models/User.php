<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'current_tenant_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    // Type Casting function
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relational Functions

    public function tenants()
    {
        // Explicit FKs because the model class is plural (Tenants), which
        // breaks Laravel's default singular-based pivot column guess
        // (it would otherwise look for tenants_id instead of tenant_id).
        return $this->belongsToMany(Tenants::class, 'tenant_user', 'user_id', 'tenant_id')
        ->withPivot('role')
        ->withTimestamps();
    }

    public function currentTenant()
    {
        return $this->belongsTo(Tenants::class,'current_tenant_id');

    }

    /**
     * Send the password reset notification, pointing at the frontend's
     * reset-password page instead of Laravel's default web route (this API
     * has no server-rendered reset form; the SPA owns that page).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification via a backend-signed URL
     * (the link is clicked directly, so it must hit this API, not the SPA).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }
}
