<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeveloperPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'developer_id',
        'project_id',
        'total_assigned_amount',
        'amount_paid',
        'status',
        'payment_notes',
        'invoice_no',
    ];

    protected $casts = [
        'total_assigned_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'developer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function paymentHistory(): HasMany
    {
        return $this->hasMany(DeveloperPaymentHistory::class);
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, ($this->total_assigned_amount ?? 0) - ($this->amount_paid ?? 0));
    }

    public function updateStatus()
    {
        $remaining = $this->remaining_amount;
        if ($remaining <= 0) {
            $this->status = 'Paid';
        } elseif ($this->amount_paid > 0) {
            $this->status = 'Partial';
        } else {
            $this->status = 'Pending';
        }
        $this->save();
    }
}

