<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Webwizardsusa\ImageCache\Enums\Visibility;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Tests\Fixtures\CustomImage;
use Webwizardsusa\ImageCache\Tests\TestCase;

uses(TestCase::class);

it('generates a missing Variant and redirects to its disk URL', function (): void {
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 400, 200));
    $path = "image-cache/thumb/{$image->path}";

    $this->get("/image-cache/thumb/{$image->ulid}")->assertRedirect(Storage::disk('public')->url($path));

    $variant = $image->variant('thumb');

    expect($variant->disk)->toBe('public')
        ->and($variant->path)->toBe($path)
        ->and($variant->width)->toBe(100)
        ->and($variant->height)->toBe(100)
        ->and($variant->mime_type)->toBe('image/jpeg');

    Storage::disk('public')->assertExists($path);
});

it('generates a Variant once however often it is requested', function (): void {
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 400, 200));

    $this->get("/image-cache/thumb/{$image->ulid}")->assertRedirect();
    $image->variant('thumb')->update(['size' => 1]);
    $this->get("/image-cache/thumb/{$image->ulid}")->assertRedirect();

    expect($image->variants()->count())->toBe(1)
        ->and($image->variant('thumb')->size)->toBe(1);
});

it('returns 404 for a style that is not registered', function (): void {
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));

    $this->get("/image-cache/missing/{$image->ulid}")->assertNotFound();
});

it('returns 404 for an Image that does not exist, or is asked for by id', function (): void {
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));

    $this->get('/image-cache/thumb/01JUNKJUNKJUNKJUNKJUNKJUNK')->assertNotFound();
    $this->get("/image-cache/thumb/{$image->id}")->assertNotFound();
});

it('returns 503 with Retry-After while another request holds the lock', function (): void {
    config(['image-cache.lock.timeout' => 0]);
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));
    Cache::lock("image-cache:{$image->id}:thumb", 60)->get();

    $this->get("/image-cache/thumb/{$image->ulid}")
        ->assertStatus(503)
        ->assertHeader('Retry-After', '1');

    expect($image->variant('thumb'))->toBeNull();
});

it('serves an existing Variant without taking the lock', function (): void {
    config(['image-cache.lock.timeout' => 0]);
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));
    $this->get("/image-cache/thumb/{$image->ulid}");
    Cache::lock("image-cache:{$image->id}:thumb", 60)->get();

    $this->get("/image-cache/thumb/{$image->ulid}")->assertRedirect();
});

it('returns 403 for a private Image without a valid signature', function (): void {
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10), Visibility::Private);

    $this->get("/image-cache/thumb/{$image->ulid}")->assertForbidden();
    $this->get("/image-cache/thumb/{$image->ulid}?expires=9999999999&signature=forged")->assertForbidden();

    expect($image->variant('thumb'))->toBeNull();
});

it('returns 403 for a private Image once its signed URL has expired', function (): void {
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10), Visibility::Private);
    $url = $image->url('thumb');

    $this->travel(6)->minutes();

    $this->get($url)->assertForbidden();
});

it('generates a private Variant on the private disk and redirects to a temporary URL', function (): void {
    $this->freezeTime();
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10), Visibility::Private);
    $path = "image-cache/thumb/{$image->path}";

    $this->get($image->url('thumb'))->assertRedirect(Storage::disk('local')->temporaryUrl($path, now()->addMinutes(5)));

    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

it('finds Images through the configured model', function (): void {
    config(['image-cache.image_model' => CustomImage::class]);
    $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));

    $this->get("/image-cache/thumb/{$image->ulid}")->assertRedirect();

    expect($image->variant('thumb')->image)->toBeInstanceOf(CustomImage::class);
});
