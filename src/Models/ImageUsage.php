<?php

namespace Webwizardsusa\ImageCache\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Webwizardsusa\ImageCache\Facades\ImageCache;

/**
 * A record's use of an Image, kept in sync by the TracksImageUsage trait.
 *
 * @property int $image_id
 * @property string $usable_type
 * @property int $usable_id
 */
class ImageUsage extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('image-cache.tables.usages', 'image_usages');
    }

    /**
     * @return BelongsTo<Image, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(ImageCache::imageModel(), 'image_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function usable(): MorphTo
    {
        return $this->morphTo();
    }
}
