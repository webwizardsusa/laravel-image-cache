<?php

namespace Webwizardsusa\ImageCache\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;
use Webwizardsusa\ImageCache\Enums\Visibility;
use Webwizardsusa\ImageCache\Facades\ImageCache;

/**
 * An original image, stored with store(). Apps that need more columns extend this class and point
 * `image-cache.image_model` at it.
 *
 * Images are immutable: replacing the file, or changing its Visibility, means storing a new Image. Routes find Images
 * by their ULID, so ids can't be guessed. Deleting an Image deletes its Variants and Usages, and its files and its
 * Variants' files once the transaction commits.
 *
 * @property int $id
 * @property string $ulid
 * @property string $disk
 * @property string $path
 * @property Visibility $visibility
 * @property string $mime_type
 * @property int $width
 * @property int $height
 * @property int $size
 * @property string $hash
 * @property Carbon|null $orphaned_at
 */
class Image extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'orphaned_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('image-cache.tables.images', 'images');
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected static function booted(): void
    {
        static::deleting(static function (Image $image): void {
            $files = $image->variants()->get(['disk', 'path'])
                ->map(fn (Variant $variant): array => [$variant->disk, $variant->path])
                ->push([$image->disk, $image->path])
                ->all();

            $image->variants()->delete();
            $image->usages()->delete();

            $image->getConnection()->afterCommit(static function () use ($files): void {
                foreach ($files as [$disk, $path]) {
                    Storage::disk($disk)->delete($path);
                }
            });
        });
    }

    /**
     * Stores a new Image as ImageCache::store() does, with the configured Image model.
     *
     * @param  UploadedFile | SplFileInfo | string  $file  an uploaded file, or the path of a local file
     */
    public static function store(UploadedFile|SplFileInfo|string $file, ?Visibility $visibility = null, ?string $directory = null): Image
    {
        return ImageCache::store($file, $visibility, $directory);
    }

    /**
     * @return HasMany<Variant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class, 'image_id');
    }

    /**
     * @return HasMany<ImageUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(ImageUsage::class, 'image_id');
    }

    /**
     * The Variant in this style, when it has been generated. Uses the loaded `variants` relation when there is one.
     */
    public function variant(string $style): ?Variant
    {
        if ($this->relationLoaded('variants')) {
            return $this->getRelation('variants')->firstWhere('style', $style);
        }

        return $this->variants()->where('style', $style)->first();
    }

    /**
     * The URL of the Variant in this style, as ImageCache::url() makes it.
     */
    public function url(string $style): string
    {
        return ImageCache::url($this, $style);
    }

    /**
     * The `srcset` of this Image in these styles, or the named set, as ImageCache::srcset() makes it.
     *
     * @param  array<string> | string  $set
     */
    public function srcset(array|string $set, ?int $maxWidth = null): string
    {
        return ImageCache::srcset($this, $set, $maxWidth);
    }

    public function isOrphaned(): bool
    {
        return $this->orphaned_at !== null;
    }
}
