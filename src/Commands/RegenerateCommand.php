<?php

namespace Webwizardsusa\ImageCache\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Generation\VariantGenerator;
use Webwizardsusa\ImageCache\Jobs\GenerateVariant;
use Webwizardsusa\ImageCache\Models\Image;

/**
 * Generates the Variants of the named Image Styles, or of every registered one, again, replacing the files in place,
 * so URLs keep working throughout. Only Variants that exist are regenerated, unless `--missing` asks for every Image's
 * Variant. With `--queue`, each one is generated on the queue set in `image-cache.queue`.
 */
class RegenerateCommand extends Command
{
    protected $signature = 'image-cache:regenerate
        {styles?* : The names of the Image Styles whose Variants to generate again (every registered one when left out)}
        {--missing : Also generate the Variants that don\'t exist yet, for every Image}
        {--queue : Generate them on the queue instead of right away}
        {--pretend : Count the Variants that would be generated, and generate nothing}';

    protected $description = 'Generate Variants again after their Image Style has changed';

    public function handle(VariantGenerator $generator): int
    {
        $styles = $this->argument('styles') ?: array_keys(ImageCache::styles());

        foreach ($styles as $style) {
            if (! ImageCache::has($style)) {
                $this->components->error("No Image Style is registered with the name [{$style}].");

                return self::FAILURE;
            }
        }

        $count = 0;
        $failed = 0;

        foreach ($styles as $style) {
            $images = $this->imagesFor($style);

            if ($this->option('pretend')) {
                $count += $images->count();

                continue;
            }

            $images->chunkById(100, function ($images) use ($style, $generator, &$count, &$failed): void {
                foreach ($images as $image) {
                    if ($this->option('queue')) {
                        GenerateVariant::dispatch($image, $style, regenerate: true);
                        $count++;

                        continue;
                    }

                    try {
                        $generator->generate($image, $style);
                        $count++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->components->warn("Image [{$image->getKey()}] in [{$style}]: {$exception->getMessage()}");
                    }
                }
            });
        }

        $variants = $count.' '.str('variant')->plural($count);

        $this->components->info(match (true) {
            (bool) $this->option('pretend') => "Would regenerate {$variants}.",
            (bool) $this->option('queue') => "Queued {$variants} to regenerate.",
            default => "Regenerated {$variants}.",
        });

        if ($failed > 0) {
            $this->components->error("{$failed} ".str('variant')->plural($failed).' failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * The Images to generate this style's Variant of: those that have one, or with `--missing` every Image.
     *
     * @return Builder<Image>
     */
    protected function imagesFor(string $style): Builder
    {
        return ImageCache::newImageQuery()
            ->unless($this->option('missing'), fn (Builder $query) => $query->whereHas('variants', fn (Builder $variants) => $variants->where('style', $style)));
    }
}
