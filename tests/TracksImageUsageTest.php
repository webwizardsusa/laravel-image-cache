<?php

use Illuminate\Http\UploadedFile;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Tests\Fixtures\Article;
use Webwizardsusa\ImageCache\Tests\TestCase;

uses(TestCase::class);

function storeUsageImage(): Image
{
    return Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));
}

it('records the Images a record references when it is saved', function (): void {
    $hero = storeUsageImage();
    $thumbnail = storeUsageImage();

    $article = Article::create(['title' => 'Hello', 'hero_image_id' => $hero->id, 'thumbnail_image_id' => $thumbnail->id]);

    expect($article->usedImages()->pluck('id')->sort()->values()->all())->toBe([$hero->id, $thumbnail->id]);
});

it('counts an Image referenced twice as one Usage', function (): void {
    $image = storeUsageImage();

    $article = Article::create(['title' => 'Hello', 'hero_image_id' => $image->id, 'thumbnail_image_id' => $image->id]);

    expect($article->usedImages()->count())->toBe(1);
});

it('orphans an Image when its last Usage goes away', function (): void {
    $this->freezeSecond();
    $image = storeUsageImage();
    $article = Article::create(['title' => 'Hello', 'hero_image_id' => $image->id]);

    $article->update(['hero_image_id' => null]);

    expect($image->fresh()->orphaned_at?->equalTo(now()))->toBeTrue()
        ->and($article->usedImages()->count())->toBe(0);
});

it('does not orphan an Image another record still uses', function (): void {
    $image = storeUsageImage();
    $first = Article::create(['title' => 'First', 'hero_image_id' => $image->id]);
    Article::create(['title' => 'Second', 'hero_image_id' => $image->id]);

    $first->update(['hero_image_id' => null]);

    expect($image->fresh()->orphaned_at)->toBeNull();
});

it('adopts an Orphaned Image again when a record uses it', function (): void {
    $image = storeUsageImage();
    $article = Article::create(['title' => 'Hello', 'hero_image_id' => $image->id]);
    $article->update(['hero_image_id' => null]);

    $article->update(['thumbnail_image_id' => $image->id]);

    expect($image->fresh()->orphaned_at)->toBeNull();
});

it('never orphans an Image that has never been used', function (): void {
    $image = storeUsageImage();

    Article::create(['title' => 'Hello']);

    expect($image->fresh()->orphaned_at)->toBeNull();
});

it('forgets its Usages when it is force deleted, and orphans the Images', function (): void {
    $image = storeUsageImage();
    $article = Article::create(['title' => 'Hello', 'hero_image_id' => $image->id]);

    $article->forceDelete();

    expect($image->fresh()->orphaned_at)->not->toBeNull()
        ->and($image->usages()->count())->toBe(0);
});

it('keeps its Usages when it is soft deleted', function (): void {
    $image = storeUsageImage();
    $article = Article::create(['title' => 'Hello', 'hero_image_id' => $image->id]);

    $article->delete();

    expect($image->fresh()->orphaned_at)->toBeNull()
        ->and($image->usages()->count())->toBe(1);
});

it('takes Images by ULID, and ignores ULIDs that match no Image', function (): void {
    $image = storeUsageImage();
    $article = Article::create(['title' => 'Hello']);

    $article->syncImageUsages([strtoupper($image->ulid), '01jzzzzzzzzzzzzzzzzzzzzzzz']);

    expect($article->usedImages()->pluck('id')->all())->toBe([$image->id]);
});
