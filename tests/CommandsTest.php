<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Jobs\GenerateVariant;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Models\Variant;
use Webwizardsusa\ImageCache\Tests\Fixtures\Article;
use Webwizardsusa\ImageCache\Tests\Fixtures\QueuedCardStyle;
use Webwizardsusa\ImageCache\Tests\Fixtures\WebpBannerStyle;
use Webwizardsusa\ImageCache\Tests\TestCase;

uses(TestCase::class);

function storeCommandImage(): Image
{
    return Image::store(UploadedFile::fake()->image('photo.jpg', 800, 600));
}

function orphanImage(Image $image): Image
{
    $article = Article::create(['title' => 'Hello', 'hero_image_id' => $image->id]);
    $article->update(['hero_image_id' => null]);

    return $image->fresh();
}

describe('image-cache:flush', function (): void {
    beforeEach(function (): void {
        ImageCache::register(WebpBannerStyle::class);
    });

    it('deletes the Variants of one style, with their files', function (): void {
        $image = storeCommandImage();
        $thumb = ImageCache::url($image, 'thumb');
        $this->get($thumb);
        $thumbPath = $image->variant('thumb')->path;

        $this->artisan('image-cache:flush', ['styles' => ['banner']])->expectsOutputToContain('Flushed 1 variant.')->assertSuccessful();

        expect(Variant::query()->pluck('style')->all())->toBe(['thumb']);
        Storage::disk('public')->assertExists($thumbPath);
        Storage::disk('public')->assertMissing('image-cache/banner/'.str($image->path)->beforeLast('.').'.webp');
    });

    it('deletes the Variants of several styles, and of styles no longer registered', function (): void {
        ImageCache::register(QueuedCardStyle::class);
        $image = storeCommandImage();
        $this->get(ImageCache::url($image, 'thumb'));
        Variant::query()->create(['image_id' => $image->id, 'style' => 'retired', 'disk' => 'public', 'path' => 'image-cache/retired/x.jpg', 'mime_type' => 'image/jpeg', 'width' => 1, 'height' => 1, 'size' => 1]);

        $this->artisan('image-cache:flush', ['styles' => ['banner', 'card', 'retired']])->expectsOutputToContain('Flushed 3 variants.')->assertSuccessful();

        expect(Variant::query()->pluck('style')->all())->toBe(['thumb']);
    });

    it('deletes the Variants of every style', function (): void {
        $image = storeCommandImage();
        $this->get(ImageCache::url($image, 'thumb'));

        $this->artisan('image-cache:flush')->expectsOutputToContain('Flushed 2 variants.')->assertSuccessful();

        expect(Variant::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([$image->path]);
    });

    it('deletes nothing when pretending', function (): void {
        storeCommandImage();

        $this->artisan('image-cache:flush', ['--pretend' => true])->expectsOutputToContain('Would flush 1 variant.')->assertSuccessful();

        expect(Variant::query()->count())->toBe(1);
    });
});

describe('image-cache:regenerate', function (): void {
    beforeEach(function (): void {
        ImageCache::register(WebpBannerStyle::class);
    });

    it('generates the existing Variants of the named styles again, in place', function (): void {
        $image = storeCommandImage();
        $variant = $image->variant('banner');
        Storage::disk('public')->put($variant->path, 'stale');
        $variant->update(['size' => 5]);

        $this->artisan('image-cache:regenerate', ['styles' => ['banner']])->expectsOutputToContain('Regenerated 1 variant.')->assertSuccessful();

        $regenerated = $image->variant('banner');

        expect($regenerated->id)->toBe($variant->id)
            ->and($regenerated->path)->toBe($variant->path)
            ->and($regenerated->size)->toBe(strlen(Storage::disk('public')->get($variant->path)))
            ->and($regenerated->size)->toBeGreaterThan(5);
    });

    it('leaves missing Variants alone, unless asked for them', function (): void {
        $image = storeCommandImage();

        $this->artisan('image-cache:regenerate', ['styles' => ['thumb']])->expectsOutputToContain('Regenerated 0 variants.')->assertSuccessful();

        expect($image->variant('thumb'))->toBeNull();

        $this->artisan('image-cache:regenerate', ['styles' => ['thumb'], '--missing' => true])->expectsOutputToContain('Regenerated 1 variant.')->assertSuccessful();

        expect($image->variant('thumb')->width)->toBe(100);
    });

    it('generates every registered style when no names are given', function (): void {
        storeCommandImage();
        storeCommandImage();

        $this->artisan('image-cache:regenerate', ['--missing' => true])->expectsOutputToContain('Regenerated 4 variants.')->assertSuccessful();

        expect(Variant::query()->where('style', 'thumb')->count())->toBe(2);
    });

    it('fails for a style that is not registered, and generates nothing', function (): void {
        $image = storeCommandImage();
        $before = $image->variant('banner')->updated_at;
        $this->travel(1)->minutes();

        $this->artisan('image-cache:regenerate', ['styles' => ['banner', 'retired']])
            ->expectsOutputToContain('No Image Style is registered with the name [retired].')
            ->assertFailed();

        expect($image->variant('banner')->updated_at->equalTo($before))->toBeTrue();
    });

    it('queues the Variants with --queue', function (): void {
        $image = storeCommandImage();
        Queue::fake([GenerateVariant::class]);

        $this->artisan('image-cache:regenerate', ['styles' => ['banner'], '--queue' => true])->expectsOutputToContain('Queued 1 variant to regenerate.')->assertSuccessful();

        Queue::assertPushed(GenerateVariant::class, fn (GenerateVariant $job): bool => $job->image->is($image) && $job->style === 'banner' && $job->regenerate);
    });

    it('regenerates an existing Variant when the queued job runs', function (): void {
        $image = storeCommandImage();
        $variant = $image->variant('banner');
        Storage::disk('public')->put($variant->path, 'stale');

        $this->artisan('image-cache:regenerate', ['styles' => ['banner'], '--queue' => true])->assertSuccessful();

        expect(Storage::disk('public')->get($variant->path))->not->toBe('stale');
    });

    it('counts without generating when pretending', function (): void {
        $image = storeCommandImage();
        Storage::disk('public')->put($image->variant('banner')->path, 'stale');

        $this->artisan('image-cache:regenerate', ['--pretend' => true, '--missing' => true])->expectsOutputToContain('Would regenerate 2 variants.')->assertSuccessful();

        expect(Storage::disk('public')->get($image->variant('banner')->path))->toBe('stale')
            ->and($image->variant('thumb'))->toBeNull();
    });

    it('carries on past a Variant that fails, and reports it', function (): void {
        $broken = storeCommandImage();
        $working = storeCommandImage();
        Storage::disk('public')->delete($broken->path);

        $this->artisan('image-cache:regenerate', ['styles' => ['banner']])
            ->expectsOutputToContain("Image [{$broken->id}] in [banner]")
            ->expectsOutputToContain('Regenerated 1 variant.')
            ->expectsOutputToContain('1 variant failed.')
            ->assertFailed();

        expect($working->variant('banner'))->not->toBeNull();
    });
});

describe('image-cache:prune', function (): void {
    it('deletes Images Orphaned for longer than the grace period', function (): void {
        $old = orphanImage(storeCommandImage());
        $this->travel(25)->hours();
        $recent = orphanImage(storeCommandImage());
        $neverUsed = storeCommandImage();

        $this->artisan('image-cache:prune')->expectsOutputToContain('Pruned 1 image.')->assertSuccessful();

        expect(Image::query()->pluck('id')->sort()->values()->all())->toBe([$recent->id, $neverUsed->id]);
        Storage::disk('public')->assertMissing($old->path);
    });

    it('uses the configured grace period', function (): void {
        config(['image-cache.prune.grace_hours' => 1]);
        orphanImage(storeCommandImage());
        $this->travel(2)->hours();

        $this->artisan('image-cache:prune')->expectsOutputToContain('Pruned 1 image.')->assertSuccessful();
    });

    it('deletes nothing when pretending', function (): void {
        $image = orphanImage(storeCommandImage());
        $this->travel(25)->hours();

        $this->artisan('image-cache:prune', ['--pretend' => true])->expectsOutputToContain('Would prune 1 image.')->assertSuccessful();

        expect($image->fresh())->not->toBeNull();
    });
});
