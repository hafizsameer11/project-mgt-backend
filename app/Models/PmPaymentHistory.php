<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmPaymentHistory extends Model
{
    use HasFactory;

    protected $table = 'pm_payment_histories';

    protected $fillable = [
        'project_pm_payment_id',
        'amount',
        'payment_date',
        'invoice_path',
        'invoice_no',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ProjectPmPayment::class, 'project_pm_payment_id');
    }
}
