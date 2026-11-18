<?php

use Illuminate\Http\UploadedFile;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Tests\Fixtures\OriginalStyle;
use Webwizardsusa\ImageCache\Tests\Fixtures\QueuedCardStyle;
use Webwizardsusa\ImageCache\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    ImageCache::register(QueuedCardStyle::class, OriginalStyle::class);
    config(['image-cache.queue.connection' => 'null']);
    $this->image = Image::store(UploadedFile::fake()->image('photo.jpg', 800, 600));
});

it('renders an img in the style, with its size and lazy loading, passing other attributes through', function (): void {
    $html = (string) $this->blade('<x-image-cache::image :image="$image" image-style="card" alt="A cat" class="rounded" style="width: 50%" />', ['image' => $this->image]);

    expect($html)->toContain('<img')
        ->toContain('src="'.$this->image->url('card').'"')
        ->toContain('width="300"')
        ->toContain('height="200"')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"')
        ->toContain('alt="A cat"')
        ->toContain('class="rounded"')
        ->toContain('style="width: 50%;"')
        ->not->toContain('srcset')
        ->not->toContain('sizes');
});

it('adds a srcset, with automatic sizes for a lazy image', function (): void {
    config(['image-cache.srcsets.hero' => ['thumb', 'card']]);

    $html = (string) $this->blade('<x-image-cache::image :image="$image" image-style="card" srcset="hero" alt="" />', ['image' => $this->image]);

    expect($html)->toContain('srcset="'.e($this->image->srcset('hero')).'"')
        ->toContain('sizes="auto, 100vw"');
});

it('uses the sizes given, and none by default for an eager image', function (): void {
    $given = (string) $this->blade('<x-image-cache::image :image="$image" image-style="card" :srcset="[\'thumb\', \'card\']" sizes="50vw" alt="" />', ['image' => $this->image]);
    $eager = (string) $this->blade('<x-image-cache::image :image="$image" image-style="card" :srcset="[\'thumb\', \'card\']" loading="eager" alt="" />', ['image' => $this->image]);

    expect($given)->toContain('sizes="50vw"')
        ->and($eager)->toContain('loading="eager"')->not->toContain('sizes=');
});

it('leaves out the size when the style declares none and the Variant does not exist yet', function (): void {
    $html = (string) $this->blade('<x-image-cache::image :image="$image" image-style="original" alt="" />', ['image' => $this->image]);

    expect($html)->not->toContain('width=')->not->toContain('height=');
});
