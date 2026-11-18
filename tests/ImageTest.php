<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Models\Variant;
use Webwizardsusa\ImageCache\Tests\Fixtures\Article;
use Webwizardsusa\ImageCache\Tests\Fixtures\WebpBannerStyle;
use Webwizardsusa\ImageCache\Tests\TestCase;

uses(TestCase::class);

it('deletes its Variants, Usages and files when it is deleted', function (): void {
    ImageCache::register(WebpBannerStyle::class);
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 800, 600));
    Article::create(['title' => 'Hello', 'hero_image_id' => $image->id]);

    $image->delete();

    expect(Variant::query()->count())->toBe(0)
        ->and(DB::table('image_usages')->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('keeps its files when the transaction deleting it rolls back', function (): void {
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));

    try {
        DB::transaction(function () use ($image): void {
            $image->delete();

            throw new RuntimeException('Roll back.');
        });
    } catch (RuntimeException) {
    }

    expect(Image::query()->whereKey($image->id)->exists())->toBeTrue();
    Storage::disk('public')->assertExists($image->path);
});

it('uses the loaded variants relation to find a Variant', function (): void {
    ImageCache::register(WebpBannerStyle::class);
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 800, 600))->load('variants');

    DB::enableQueryLog();

    expect($image->variant('banner'))->not->toBeNull()
        ->and($image->variant('thumb'))->toBeNull()
        ->and(DB::getQueryLog())->toBe([]);
});
