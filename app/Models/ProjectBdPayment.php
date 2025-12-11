<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBdPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'bd_id',
        'payment_type',
        'percentage',
        'fixed_amount',
        'calculated_amount',
        'amount_paid',
        'status',
        'payment_notes',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'calculated_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bd_id');
    }

    public function paymentHistory(): HasMany
    {
        return $this->hasMany(BdPaymentHistory::class, 'bd_payment_id');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, ($this->calculated_amount ?? 0) - ($this->amount_paid ?? 0));
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

    // Auto-calculate amount based on payment type and project budget
    public function calculateAmount()
    {
        if (!$this->project) {
            return;
        }

        if ($this->payment_type === 'percentage' && $this->percentage && $this->project->budget) {
            $this->calculated_amount = ($this->project->budget * $this->percentage) / 100;
        } elseif ($this->payment_type === 'fixed_amount' && $this->fixed_amount) {
            $this->calculated_amount = $this->fixed_amount;
        }
    }
}

