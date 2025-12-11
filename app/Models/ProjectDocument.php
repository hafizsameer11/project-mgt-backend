<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ProjectDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'type',
        'description',
        'file_path',
        'credentials',
        'url',
        'notes',
        'uploaded_by',
    ];

    protected $hidden = [
        'credentials',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function setCredentialsAttribute($value)
    {
        if ($value && is_array($value)) {
            $this->attributes['credentials'] = Crypt::encryptString(json_encode($value));
        } elseif ($value) {
            $this->attributes['credentials'] = Crypt::encryptString($value);
        }
    }

    public function getCredentialsAttribute($value)
    {
        if ($value) {
            try {
                $decrypted = Crypt::decryptString($value);
                $json = json_decode($decrypted, true);
                return $json ?: $decrypted;
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
}

