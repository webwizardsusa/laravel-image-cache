<?php

namespace Webwizardsusa\ImageCache\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Models\Image;

/**
 * Deletes Images that have been Orphaned (they had Usages and lost the last one) for longer than
 * `image-cache.prune.grace_hours`, with their Variants and files. Images that never had a Usage are left alone.
 */
class PruneCommand extends Command
{
    protected $signature = 'image-cache:prune
        {--pretend : List the Images that would be deleted, and delete nothing}';

    protected $description = 'Delete Images that are no longer used';

    public function handle(): int
    {
        $query = $this->orphanedImages();

        if ($this->option('pretend')) {
            $images = $query->orderBy('id')->get();

            $this->table(['Image', 'Path', 'Orphaned at'], $images->map(fn ($image): array => [$image->getKey(), "{$image->disk}:{$image->path}", $image->orphaned_at])->all());
            $this->components->info("Would prune {$images->count()} ".str('image')->plural($images->count()).'.');

            return self::SUCCESS;
        }

        $count = 0;

        $query->chunkById(500, function ($images) use (&$count): void {
            $images->first()->getConnection()->transaction(function () use ($images, &$count): void {
                foreach ($images as $image) {
                    $image->delete();
                    $count++;
                }
            });
        });

        $this->components->info("Pruned {$count} ".str('image')->plural($count).'.');

        return self::SUCCESS;
    }

    /**
     * @return Builder<Image>
     */
    protected function orphanedImages(): Builder
    {
        $usages = config('image-cache.tables.usages', 'image_usages');
        $images = ImageCache::newImageQuery();

        return $images
            ->whereNotNull('orphaned_at')
            ->where('orphaned_at', '<=', now()->subHours((int) config('image-cache.prune.grace_hours', 24)))
            ->whereNotExists(fn (QueryBuilder $query) => $query->from($usages)->whereColumn("{$usages}.image_id", $images->qualifyColumn('id')));
    }
}
