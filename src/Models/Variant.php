<?php

namespace Webwizardsusa\ImageCache\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webwizardsusa\ImageCache\Facades\ImageCache;

/**
 * One Image rendered in one Image Style, recorded with the disk and path it was written to. An Image has at most one
 * Variant per style.
 *
 * @property int $id
 * @property int $image_id
 * @property string $style
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $width
 * @property int $height
 * @property int $size
 */
class Variant extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'image_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('image-cache.tables.variants', 'image_variants');
    }

    /**
     * @return BelongsTo<Image, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(ImageCache::imageModel(), 'image_id');
    }
}
