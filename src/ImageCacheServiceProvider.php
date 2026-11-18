<?php

namespace Webwizardsusa\ImageCache;

use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Webwizardsusa\ImageCache\Commands\FlushCommand;
use Webwizardsusa\ImageCache\Commands\PruneCommand;
use Webwizardsusa\ImageCache\Commands\RegenerateCommand;

/**
 * Registers the config (`config/image-cache.php`), the registry with the config's Image Styles, the route that
 * generates missing Variants, the `<x-image-cache::image>` component, and the `image-cache:flush`,
 * `image-cache:regenerate` and `image-cache:prune` commands. The migration is only published
 * (`--tag=image-cache-migrations`), never run from the package.
 */
class ImageCacheServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('image-cache')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web')
            ->hasMigration('create_image_cache_tables')
            ->hasCommands([FlushCommand::class, PruneCommand::class, RegenerateCommand::class]);
    }

    public function packageBooted(): void
    {
        Blade::componentNamespace('Webwizardsusa\\ImageCache\\View\\Components', 'image-cache');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ImageCache::class, static function ($app): ImageCache {
            $imageCache = new ImageCache($app);
            $imageCache->register(...$app['config']->get('image-cache.styles', []));

            return $imageCache;
        });
    }
}
