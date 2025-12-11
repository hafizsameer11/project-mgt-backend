<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'address',
        'website',
        'notes',
        'assigned_bd',
        'status',
    ];

    public function assignedBd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_bd');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function passwordVaults(): HasMany
    {
        return $this->hasMany(PasswordVault::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }
}

