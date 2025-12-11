<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'role',
        'payment_type',
        'salary_amount',
        'skills',
        'joining_date',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'salary_amount' => 'decimal:2',
        'joining_date' => 'date',
        'skills' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_team')
            ->withPivot('assigned_amount')
            ->withTimestamps();
    }

    public function developerPayments(): HasMany
    {
        return $this->hasMany(DeveloperPayment::class, 'developer_id');
    }
}

