<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperPaymentHistory extends Model
{
    use HasFactory;

    protected $table = 'developer_payment_histories';

    protected $fillable = [
        'developer_payment_id',
        'amount',
        'payment_date',
        'notes',
        'invoice_path',
        'invoice_no',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function developerPayment(): BelongsTo
    {
        return $this->belongsTo(DeveloperPayment::class);
    }
}

