<?php

namespace Webwizardsusa\ImageCache\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Models\Image;

/**
 * Records which Images a record uses, from referencedImages(), every time it's saved, and forgets them when it's
 * deleted (not when it's soft deleted). An Image whose last Usage goes away becomes Orphaned, and image-cache:prune
 * deletes it once the grace period has passed. Images that never had a Usage are never Orphaned.
 *
 * @mixin Model
 */
trait TracksImageUsage
{
    /**
     * The Images the record uses right now, as models, ids or ULIDs. imagesFromAttributes() covers foreign key columns.
     *
     * @return iterable<Image|int|string|null>
     */
    abstract public function referencedImages(): iterable;

    public static function bootTracksImageUsage(): void
    {
        static::saved(static function (Model $record): void {
            $record->syncImageUsages();
        });

        static::deleted(static function (Model $record): void {
            if (! method_exists($record, 'isForceDeleting') || $record->isForceDeleting()) {
                $record->syncImageUsages([]);
            }
        });
    }

    /**
     * @return MorphToMany<Image, $this>
     */
    public function usedImages(): MorphToMany
    {
        return $this->morphToMany(ImageCache::imageModel(), 'usable', config('image-cache.tables.usages', 'image_usages'), 'usable_id', 'image_id')
            ->withTimestamps();
    }

    /**
     * Makes the given Images (by default, referencedImages()) the record's only Usages, and marks the Images that
     * gained their first Usage or lost their last one.
     *
     * @param  iterable<Image|int|string|null> | null  $images
     */
    public function syncImageUsages(?iterable $images = null): void
    {
        $ids = [];
        $ulids = [];

        foreach ($images ?? $this->referencedImages() as $image) {
            if ($image instanceof Image) {
                $ids[] = (int) $image->getKey();
            } elseif (is_int($image) || (is_string($image) && ctype_digit($image))) {
                $ids[] = (int) $image;
            } elseif (is_string($image) && $image !== '') {
                $ulids[] = strtolower($image);
            }
        }

        if ($ulids !== []) {
            // ULIDs that match no Image are ignored, like content pointing at an Image that has since been deleted.
            array_push($ids, ...ImageCache::newImageQuery()->whereIn('ulid', array_unique($ulids))->pluck('id')->map(fn (mixed $id): int => (int) $id));
        }

        $ids = array_values(array_unique($ids));

        $this->getConnection()->transaction(function () use ($ids): void {
            $changes = $this->usedImages()->sync($ids);
            $usages = config('image-cache.tables.usages', 'image_usages');

            if ($changes['attached'] !== []) {
                ImageCache::newImageQuery()->whereKey($changes['attached'])->whereNotNull('orphaned_at')->update(['orphaned_at' => null]);
            }

            if ($changes['detached'] !== []) {
                $images = ImageCache::newImageQuery();

                $images->whereKey($changes['detached'])
                    ->whereNull('orphaned_at')
                    ->whereNotExists(fn (Builder $query) => $query->from($usages)->whereColumn("{$usages}.image_id", $images->qualifyColumn('id')))
                    ->update(['orphaned_at' => now()]);
            }
        });

        $this->unsetRelation('usedImages');
    }

    /**
     * The values of the given attributes, for records that point at Images with foreign key columns.
     *
     * @return array<int, int|string|null>
     */
    protected function imagesFromAttributes(string ...$attributes): array
    {
        return array_map(fn (string $attribute): mixed => $this->getAttribute($attribute), $attributes);
    }
}
