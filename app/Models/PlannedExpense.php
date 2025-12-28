<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannedExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_category_id',
        'name',
        'description',
        'amount',
        'currency',
        'day_of_month',
        'is_active',
        'is_recurring',
        'start_date',
        'end_date',
        'specific_month',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'day_of_month' => 'integer',
        'is_active' => 'boolean',
        'is_recurring' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'specific_month' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * Check if this planned expense applies to a given month
     */
    public function appliesToMonth($year, $month): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->is_recurring) {
            // Check if within start/end date range
            $checkDate = \Carbon\Carbon::create($year, $month, 1);
            
            if ($this->start_date && $checkDate->lt($this->start_date->startOfMonth())) {
                return false;
            }
            
            if ($this->end_date && $checkDate->gt($this->end_date->endOfMonth())) {
                return false;
            }
            
            return true;
        } else {
            // One-time expense - check if specific_month matches
            if (!$this->specific_month) {
                return false;
            }
            
            $specificDate = \Carbon\Carbon::parse($this->specific_month);
            return $specificDate->year == $year && $specificDate->month == $month;
        }
    }
}
