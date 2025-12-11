<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BdPaymentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'bd_payment_id',
        'amount',
        'payment_date',
        'notes',
        'invoice_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function bdPayment(): BelongsTo
    {
        return $this->belongsTo(ProjectBdPayment::class, 'bd_payment_id');
    }
}

