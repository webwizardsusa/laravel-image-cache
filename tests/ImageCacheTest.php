<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Webwizardsusa\ImageCache\Enums\Visibility;
use Webwizardsusa\ImageCache\Exceptions\InvalidImageException;
use Webwizardsusa\ImageCache\Exceptions\InvalidStyleException;
use Webwizardsusa\ImageCache\Exceptions\TemporaryUrlsNotSupportedException;
use Webwizardsusa\ImageCache\Exceptions\UnknownStyleException;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Jobs\GenerateVariant;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Models\Variant;
use Webwizardsusa\ImageCache\Tests\Fixtures\CustomImage;
use Webwizardsusa\ImageCache\Tests\Fixtures\FailingStyle;
use Webwizardsusa\ImageCache\Tests\Fixtures\QueuedCardStyle;
use Webwizardsusa\ImageCache\Tests\Fixtures\ThumbStyle;
use Webwizardsusa\ImageCache\Tests\Fixtures\WebpBannerStyle;
use Webwizardsusa\ImageCache\Tests\TestCase;

uses(TestCase::class);

describe('styles', function (): void {
    it('finds a style by name, class or instance', function (): void {
        $style = ImageCache::style('thumb');

        expect($style)->toBeInstanceOf(ThumbStyle::class)
            ->and(ImageCache::style(ThumbStyle::class))->toBe($style)
            ->and(ImageCache::style($style))->toBe($style);
    });

    it('rejects a class that is not an Image Style', function (): void {
        ImageCache::register(Image::class);
    })->throws(InvalidStyleException::class, "isn't an Image Style");

    it('rejects two styles with the same name', function (): void {
        ImageCache::register(new class extends ThumbStyle {}::class);
    })->throws(InvalidStyleException::class, 'both have the name [thumb]');

    it('rejects a name that is not a slug', function (): void {
        ImageCache::register(new class extends ThumbStyle
        {
            public static function name(): string
            {
                return 'Big Thumb';
            }
        }::class);
    })->throws(InvalidStyleException::class, 'may only have lowercase letters');

    it('throws for a style that is not registered', function (): void {
        ImageCache::style('missing');
    })->throws(UnknownStyleException::class, '[missing]');
});

describe('storing', function (): void {
    it('writes the original to the public disk and records it', function (): void {
        $image = Image::store(UploadedFile::fake()->image('photo.jpg', 800, 600));

        expect($image->disk)->toBe('public')
            ->and($image->path)->toMatch('#^images/[A-Za-z0-9]{40}\.jpg$#')
            ->and($image->visibility)->toBe(Visibility::Public)
            ->and($image->mime_type)->toBe('image/jpeg')
            ->and($image->width)->toBe(800)
            ->and($image->height)->toBe(600)
            ->and($image->size)->toBe(strlen(Storage::disk('public')->get($image->path)))
            ->and($image->hash)->toBe(hash('sha256', Storage::disk('public')->get($image->path)))
            ->and($image->ulid)->toBeString()->toHaveLength(26)
            ->and($image->orphaned_at)->toBeNull();
    });

    it('takes the extension from the contents, not the file name', function (): void {
        $png = UploadedFile::fake()->image('photo.png', 10, 10);
        $file = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents($png->getRealPath()));

        expect(Image::store($file)->path)->toEndWith('.png');
    });

    it('writes a private Image to the private disk, in the given directory', function (): void {
        $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10), Visibility::Private, 'avatars');

        expect($image->disk)->toBe('local')
            ->and($image->visibility)->toBe(Visibility::Private)
            ->and($image->path)->toStartWith('avatars/');

        Storage::disk('local')->assertExists($image->path);
    });

    it('rejects a file that is not an image, and stores nothing', function (): void {
        try {
            Image::store(UploadedFile::fake()->createWithContent('photo.jpg', 'not an image'));
        } finally {
            expect(Image::query()->count())->toBe(0)
                ->and(Storage::disk('public')->allFiles())->toBe([]);
        }
    })->throws(InvalidImageException::class);

    it('generates the Variants of OnCreate styles while storing', function (): void {
        ImageCache::register(WebpBannerStyle::class);

        $image = Image::store(UploadedFile::fake()->image('photo.jpg', 800, 600));
        $variant = $image->variant('banner');

        expect($variant->mime_type)->toBe('image/webp')
            ->and($variant->width)->toBe(400)
            ->and($variant->height)->toBe(300)
            ->and($variant->path)->toBe('image-cache/banner/'.str($image->path)->beforeLast('.').'.webp')
            ->and($image->variant('thumb'))->toBeNull();

        Storage::disk('public')->assertExists($variant->path);
    });

    it('deletes the Image again when an OnCreate Variant fails', function (): void {
        ImageCache::register(FailingStyle::class);

        try {
            Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));
        } finally {
            expect(Image::query()->count())->toBe(0)
                ->and(Storage::disk('public')->allFiles())->toBe([]);
        }
    })->throws(RuntimeException::class, 'The style failed.');

    it('queues the Variants of Queued styles', function (): void {
        Queue::fake([GenerateVariant::class]);
        ImageCache::register(QueuedCardStyle::class);

        $image = Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10));

        Queue::assertPushed(GenerateVariant::class, 1);
        Queue::assertPushed(fn (GenerateVariant $job): bool => $job->image->is($image) && $job->style === 'card');
    });

    it('generates the Variant when the queued job runs', function (): void {
        ImageCache::register(QueuedCardStyle::class);

        $image = Image::store(UploadedFile::fake()->image('photo.jpg', 600, 600));

        expect($image->variant('card')->width)->toBe(300);
    });

    it('stores Images as the configured model', function (): void {
        config(['image-cache.image_model' => CustomImage::class]);

        expect(Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10)))->toBeInstanceOf(CustomImage::class);
    });

    it('rejects an Image model that does not extend Image', function (): void {
        config(['image-cache.image_model' => Variant::class]);

        ImageCache::imageModel();
    })->throws(InvalidArgumentException::class, 'must extend');
});

describe('urls', function (): void {
    it('points at the route while the Variant is missing, and at the disk once it exists', function (): void {
        $image = Image::store(UploadedFile::fake()->image('photo.jpg', 200, 200));

        expect($image->url('thumb'))->toBe("http://localhost/image-cache/thumb/{$image->ulid}");

        $this->get($image->url('thumb'));

        expect($image->url('thumb'))->toBe(Storage::disk('public')->url("image-cache/thumb/{$image->path}"));
    });

    it('signs the route URL of a private Image', function (): void {
        $image = Image::store(UploadedFile::fake()->image('photo.jpg', 200, 200), Visibility::Private);

        expect($image->url('thumb'))->toStartWith("http://localhost/image-cache/thumb/{$image->ulid}?expires=")
            ->toContain('&signature=');
    });

    it('gives an existing private Variant a temporary disk URL', function (): void {
        $this->freezeTime();
        config(['image-cache.private.url_ttl' => 10]);
        $image = Image::store(UploadedFile::fake()->image('photo.jpg', 200, 200), Visibility::Private);
        $this->get($image->url('thumb'));

        expect($image->url('thumb'))->toBe(Storage::disk('local')->temporaryUrl("image-cache/thumb/{$image->path}", now()->addMinutes(10)));
    });

    it('throws for a private Image when the disk cannot make temporary URLs', function (): void {
        config([
            'filesystems.disks.plain' => ['driver' => 'local', 'root' => storage_path('framework/testing/plain')],
            'image-cache.disks.private.variants' => 'plain',
        ]);

        Image::store(UploadedFile::fake()->image('photo.jpg', 10, 10), Visibility::Private)->url('thumb');
    })->throws(TemporaryUrlsNotSupportedException::class, '[plain]');
});
