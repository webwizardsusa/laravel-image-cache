<?php

namespace Webwizardsusa\ImageCache\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Webwizardsusa\ImageCache\ImageCacheServiceProvider;
use Webwizardsusa\ImageCache\Tests\Fixtures\ThumbStyle;

/**
 * Boots the package with the `thumb` fixture Image Style, fakes the `public` and `local` disks that public and private
 * Images are written to, and creates the image cache tables and an `articles` table whose records use Images.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    /**
     * @param  Application  $app
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ImageCacheServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('image-cache.styles', [ThumbStyle::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        (include __DIR__.'/../database/migrations/create_image_cache_tables.php.stub')->up();

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->foreignId('hero_image_id')->nullable();
            $table->foreignId('thumbnail_image_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
