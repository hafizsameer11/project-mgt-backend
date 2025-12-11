<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'source',
        'estimated_budget',
        'lead_status',
        'assigned_to',
        'notes',
        'follow_up_date',
        'attachments',
        'conversion_date',
        'converted_client_id',
        'project_id_after_conversion',
    ];

    protected $casts = [
        'estimated_budget' => 'decimal:2',
        'follow_up_date' => 'date',
        'conversion_date' => 'date',
        'attachments' => 'array',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function projectAfterConversion(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id_after_conversion');
    }
}

