<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvancePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'monthly_salary',
        'amount',
        'currency',
        'payment_date',
        'description',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'monthly_salary' => 'decimal:2',
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
