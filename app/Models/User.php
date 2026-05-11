<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'avatar',
        'address',
        'latitude',
        'longitude',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    // Rescue Profile Relationship
    public function rescueProfile(): HasOne
    {
        return $this->hasOne(RescueProfile::class);
    }

    // Coordinator Profile Relationship
    public function coordinatorProfile(): HasOne
    {
        return $this->hasOne(CoordinatorProfile::class);
    }

    // Relief Requests as Citizen
    public function reliefRequests(): HasMany
    {
        return $this->hasMany(ReliefRequest::class, 'citizen_id');
    }

    // Relief Requests as Coordinator
    public function coordinatedRequests(): HasMany
    {
        return $this->hasMany(ReliefRequest::class, 'coordinator_id');
    }

    // Assignments
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'rescue_team_id');
    }

    // Campaigns
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'coordinator_id');
    }

    // Donations
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    // Warehouses
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'coordinator_id');
    }

    // Distributions
    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class, 'coordinator_id');
    }

    public function rescueTeamDistributions(): HasMany
    {
        return $this->hasMany(Distribution::class, 'rescue_team_id');
    }

}

