<?php

use Illuminate\Http\UploadedFile;
use Webwizardsusa\ImageCache\Exceptions\UnknownStyleException;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Tests\Fixtures\OriginalStyle;
use Webwizardsusa\ImageCache\Tests\Fixtures\QueuedCardStyle;
use Webwizardsusa\ImageCache\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    ImageCache::register(OriginalStyle::class);
});

function srcsetImage(int $width = 800, int $height = 600): Image
{
    return Image::store(UploadedFile::fake()->image('photo.jpg', $width, $height));
}

it('lists the candidates narrowest first, with their URLs and declared widths before they are generated', function (): void {
    ImageCache::register(QueuedCardStyle::class);
    $image = srcsetImage();

    expect($image->srcset(['card', 'thumb']))->toBe(
        "http://localhost/image-cache/thumb/{$image->ulid} 100w, ".$image->url('card').' 300w',
    );
});

it('uses a generated Variant\'s own width, and leaves out a style with neither', function (): void {
    $image = srcsetImage(1000, 500);

    expect(ImageCache::srcsetCandidates($image, ['thumb', 'original']))->toHaveCount(1);

    $this->get($image->url('original'));

    expect(ImageCache::srcsetCandidates($image->fresh(), ['thumb', 'original']))->toBe([
        ['style' => 'thumb', 'url' => "http://localhost/image-cache/thumb/{$image->ulid}", 'width' => 100],
        ['style' => 'original', 'url' => $image->fresh()->url('original'), 'width' => 1000],
    ]);
});

it('keeps one candidate per width, the first style given', function (): void {
    $image = srcsetImage(100, 100);
    $this->get($image->url('original'));

    expect(array_column(ImageCache::srcsetCandidates($image->fresh(), ['original', 'thumb']), 'style'))->toBe(['original']);
});

it('leaves out candidates wider than the cap', function (): void {
    ImageCache::register(QueuedCardStyle::class);
    $image = srcsetImage();

    expect(array_column(ImageCache::srcsetCandidates($image, ['thumb', 'card'], maxWidth: 150), 'style'))->toBe(['thumb']);
});

it('takes the name of a set from the config', function (): void {
    ImageCache::register(QueuedCardStyle::class);
    config(['image-cache.srcsets.hero' => ['thumb', 'card']]);
    $image = srcsetImage();

    expect($image->srcset('hero'))->toBe($image->srcset(['thumb', 'card']));
});

it('refuses a set that is not configured, and a style that is not registered', function (): void {
    expect(fn () => srcsetImage()->srcset('hero'))->toThrow(InvalidArgumentException::class, '[hero]')
        ->and(fn () => srcsetImage()->srcset(['thumb', 'huge']))->toThrow(UnknownStyleException::class);
});

it('gives the dimensions of the Variant, or of the style\'s declared size', function (): void {
    ImageCache::register(QueuedCardStyle::class);
    config(['image-cache.queue.connection' => 'null']);
    $image = srcsetImage(800, 400);

    expect(ImageCache::dimensions($image, 'card'))->toBe(['width' => 300, 'height' => 200])
        ->and(ImageCache::dimensions($image, 'original'))->toBeNull();

    $this->get($image->url('original'));

    expect(ImageCache::dimensions($image->fresh(), 'original'))->toBe(['width' => 800, 'height' => 400]);
});

it('keeps the Image\'s aspect ratio at a declared width without a declared height', function (): void {
    ImageCache::register(new class extends OriginalStyle
    {
        public static function name(): string
        {
            return 'half';
        }

        public function width(): ?int
        {
            return 200;
        }
    }::class);

    expect(ImageCache::dimensions(srcsetImage(800, 400), 'half'))->toBe(['width' => 200, 'height' => 100]);
});
