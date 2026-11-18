<?php

namespace Webwizardsusa\ImageCache\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Webwizardsusa\ImageCache\Models\Variant;

/**
 * Deletes the Variants of the named Image Styles, or of all of them, after a style has changed. They're generated
 * again the next time they're requested. Styles that are no longer registered can be flushed by name too.
 */
class FlushCommand extends Command
{
    protected $signature = 'image-cache:flush
        {styles?* : The names of the Image Styles whose Variants to delete (all of them when left out)}
        {--pretend : Count the Variants that would be deleted, and delete nothing}';

    protected $description = 'Delete generated Variants so they are generated again';

    public function handle(): int
    {
        $styles = $this->argument('styles');
        $query = Variant::query()->when($styles !== [], fn ($query) => $query->whereIn('style', $styles));

        if ($this->option('pretend')) {
            $count = $query->count();
            $this->components->info("Would flush {$count} ".str('variant')->plural($count).'.');

            return self::SUCCESS;
        }

        $count = 0;

        $query->chunkById(500, function ($variants) use (&$count): void {
            foreach ($variants as $variant) {
                Storage::disk($variant->disk)->delete($variant->path);
            }

            Variant::query()->whereKey($variants->modelKeys())->delete();
            $count += $variants->count();
        });

        $this->components->info("Flushed {$count} ".str('variant')->plural($count).'.');

        return self::SUCCESS;
    }
}
