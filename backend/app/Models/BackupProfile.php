<?php

namespace App\Models;

use Database\Factories\BackupProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupProfile extends Model
{
    /** @use HasFactory<BackupProfileFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['encrypted_configuration'];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_verified_at' => 'datetime',
        ];
    }
}
