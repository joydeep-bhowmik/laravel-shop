<?php

namespace App\Models;

use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
#[Fillable(['title', 'subtitle', 'cta_label', 'cta_url', 'position', 'is_active', 'starts_at', 'ends_at'])]
class Banner extends Model implements HasMedia
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Register the banner image collection.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    /**
     * Banners that are enabled and inside their display window.
     *
     * @param  Builder<Banner>  $query
     * @return Builder<Banner>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('position');
    }
}
