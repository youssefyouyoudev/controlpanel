<?php

namespace App\Models;

use Database\Factories\WebsiteLogSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteLogSource extends Model
{
    /** @use HasFactory<WebsiteLogSourceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['absolute_path', 'absolute_path_hash'];

    protected static function booted(): void
    {
        static::saving(function (WebsiteLogSource $source): void {
            if ($source->absolute_path) {
                $path = str_replace('\\', DIRECTORY_SEPARATOR, trim((string) $source->absolute_path));
                $source->absolute_path = rtrim(realpath($path) ?: $path, DIRECTORY_SEPARATOR);
                $source->absolute_path_hash = hash('sha256', $source->absolute_path);
            }
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(WebsiteComponent::class, 'website_component_id');
    }

    protected function casts(): array
    {
        return [
            'download_enabled' => 'boolean',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
            'configuration' => 'array',
        ];
    }
}
