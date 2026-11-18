<?php

namespace Webwizardsusa\ImageCache;

use DateTimeInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Exceptions\InvalidArgumentException as InterventionArgumentException;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use SplFileInfo;
use Throwable;
use Webwizardsusa\ImageCache\Enums\GenerationStrategy;
use Webwizardsusa\ImageCache\Enums\Visibility;
use Webwizardsusa\ImageCache\Exceptions\InvalidImageException;
use Webwizardsusa\ImageCache\Exceptions\InvalidStyleException;
use Webwizardsusa\ImageCache\Exceptions\TemporaryUrlsNotSupportedException;
use Webwizardsusa\ImageCache\Exceptions\UnknownStyleException;
use Webwizardsusa\ImageCache\Generation\VariantGenerator;
use Webwizardsusa\ImageCache\Jobs\GenerateVariant;
use Webwizardsusa\ImageCache\Models\Image;
use Webwizardsusa\ImageCache\Models\Variant;

/**
 * The registry of Image Styles, keyed by the name each one declares, and the way to store Images, find their URLs and
 * reach the configured Image model.
 */
class ImageCache
{
    /**
     * @var array<string, class-string<ImageStyle>>
     */
    protected array $classes = [];

    /**
     * @var array<string, ImageStyle>
     */
    protected array $instances = [];

    protected ?ImageManager $imageManager = null;

    public function __construct(protected Container $container) {}

    /**
     * @param  class-string<ImageStyle>  ...$classes
     */
    public function register(string ...$classes): void
    {
        foreach ($classes as $class) {
            if (! is_a($class, ImageStyle::class, true)) {
                throw new InvalidStyleException("[{$class}] isn't an Image Style: it must extend ".ImageStyle::class.'.');
            }

            $name = $class::name();

            if (preg_match('/^[a-z0-9_-]+$/', $name) !== 1) {
                throw new InvalidStyleException("[{$class}] has the name [{$name}]: names may only have lowercase letters, digits, `-` and `_`.");
            }

            if (isset($this->classes[$name]) && $this->classes[$name] !== $class) {
                throw new InvalidStyleException("[{$class}] and [{$this->classes[$name]}] both have the name [{$name}].");
            }

            $this->classes[$name] = $class;
            $this->instances[$name] = $this->container->make($class);
        }
    }

    /**
     * @param  string | ImageStyle  $style  a name, an Image Style class or an instance
     */
    public function style(string|ImageStyle $style): ImageStyle
    {
        $name = $this->nameFor($style);

        if (! isset($this->instances[$name])) {
            throw new UnknownStyleException("No Image Style is registered with the name [{$name}].");
        }

        return $this->instances[$name];
    }

    public function has(string|ImageStyle $style): bool
    {
        return isset($this->instances[$this->nameFor($style)]);
    }

    /**
     * @return array<string, ImageStyle>
     */
    public function styles(): array
    {
        return $this->instances;
    }

    /**
     * @return class-string<Image>
     */
    public function imageModel(): string
    {
        $class = config('image-cache.image_model', Image::class);

        if (! is_a($class, Image::class, true)) {
            throw new InvalidArgumentException("The image-cache.image_model [{$class}] must extend ".Image::class.'.');
        }

        return $class;
    }

    /**
     * @return Builder<Image>
     */
    public function newImageQuery(): Builder
    {
        return $this->imageModel()::query();
    }

    /**
     * The Intervention image manager, with the driver set in `image-cache.driver`.
     */
    public function imageManager(): ImageManager
    {
        return $this->imageManager ??= new ImageManager(match ($driver = config('image-cache.driver', 'gd')) {
            'gd' => GdDriver::class,
            'imagick' => ImagickDriver::class,
            'vips' => 'Intervention\Image\Drivers\Vips\Driver',
            default => $driver,
        });
    }

    /**
     * Stores a new Image: writes the original under a random name on the disk for its Visibility, then generates the
     * Variants of OnCreate Image Styles and queues those of Queued ones. When an OnCreate Variant can't be generated,
     * the Image is deleted again and the exception is rethrown.
     *
     * @param  UploadedFile | SplFileInfo | string  $file  an uploaded file, or the path of a local file
     */
    public function store(UploadedFile|SplFileInfo|string $file, ?Visibility $visibility = null, ?string $directory = null): Image
    {
        $visibility ??= Visibility::from(config('image-cache.default_visibility', 'public'));
        $contents = @file_get_contents($file instanceof SplFileInfo ? $file->getRealPath() : $file);

        if ($contents === false) {
            throw new InvalidImageException('The file ['.($file instanceof SplFileInfo ? $file->getFilename() : $file).'] can\'t be read.');
        }

        try {
            $decoded = $this->imageManager()->decodeBinary($contents);
            $format = Format::create($decoded->origin()->mediaType());
        } catch (DecoderException|InterventionArgumentException $exception) {
            throw new InvalidImageException('The file isn\'t an image that can be read.', previous: $exception);
        }

        $disk = $this->diskFor($visibility, 'originals');
        $directory = trim($directory ?? config('image-cache.originals_directory', 'images'), '/');
        $path = ltrim($directory.'/'.Str::random(40).'.'.$format->fileExtension()->value, '/');

        Storage::disk($disk)->put($path, $contents);

        $model = $this->imageModel();

        try {
            $image = $model::query()->create([
                'disk' => $disk,
                'path' => $path,
                'visibility' => $visibility,
                'mime_type' => $format->mediaType()->value,
                'width' => $decoded->width(),
                'height' => $decoded->height(),
                'size' => strlen($contents),
                'hash' => hash('sha256', $contents),
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        try {
            foreach ($this->styles() as $name => $style) {
                if ($style->strategy() === GenerationStrategy::OnCreate) {
                    $this->container->make(VariantGenerator::class)->generate($image, $name);
                }
            }
        } catch (Throwable $exception) {
            $image->delete();

            throw $exception;
        }

        foreach ($this->styles() as $name => $style) {
            if ($style->strategy() === GenerationStrategy::Queued) {
                GenerateVariant::dispatch($image, $name)->afterCommit();
            }
        }

        return $image->unsetRelation('variants');
    }

    /**
     * The URL of an Image's Variant in this style. A Variant that exists is served by its disk. A missing one is
     * served by the package's route, which generates it and redirects to it. Private Images get temporary URLs.
     */
    public function url(Image $image, string|ImageStyle $style): string
    {
        $name = $this->style($style)::name();
        $variant = $image->variant($name);

        if ($variant !== null) {
            return $this->variantUrl($variant, $image->visibility);
        }

        if ($image->visibility === Visibility::Public) {
            return URL::route(config('image-cache.route.name', 'image-cache.variant'), ['style' => $name, 'image' => $image->getRouteKey()]);
        }

        // Fails here rather than after the redirect, so a disk that can't serve the Variant is found right away.
        $this->ensureTemporaryUrls($this->diskFor(Visibility::Private, 'variants'));

        return URL::temporarySignedRoute(
            config('image-cache.route.name', 'image-cache.variant'),
            $this->expiration(),
            ['style' => $name, 'image' => $image->getRouteKey()],
        );
    }

    /**
     * The styles of a srcset: a list of style names or classes, or the name of a set in `image-cache.srcsets`.
     *
     * @param  array<string|ImageStyle> | string  $set
     * @return array<int, string>
     *
     * @throws InvalidArgumentException for a set name that isn't configured
     * @throws UnknownStyleException for a style that isn't registered
     */
    public function srcsetStyles(array|string $set): array
    {
        if (is_string($set)) {
            $styles = config("image-cache.srcsets.{$set}");

            if (! is_array($styles)) {
                throw new InvalidArgumentException("No srcset is configured with the name [{$set}] in image-cache.srcsets.");
            }

            $set = $styles;
        }

        return array_values(array_map(fn (string|ImageStyle $style): string => $this->style($style)::name(), $set));
    }

    /**
     * The candidates of an Image's srcset, narrowest first: each style's URL and width. The width is the Variant's
     * once it exists, otherwise the one the style declares; a style with neither is left out, as are widths over
     * `$maxWidth` and widths an earlier style already has.
     *
     * @param  array<string|ImageStyle> | string  $set
     * @return array<int, array{style: string, url: string, width: int}>
     */
    public function srcsetCandidates(Image $image, array|string $set, ?int $maxWidth = null): array
    {
        $candidates = [];

        foreach ($this->srcsetStyles($set) as $style) {
            $width = $image->variant($style)?->width ?? $this->style($style)->width();

            if ($width === null || ($maxWidth !== null && $width > $maxWidth) || isset($candidates[$width])) {
                continue;
            }

            $candidates[$width] = ['style' => $style, 'url' => $this->url($image, $style), 'width' => $width];
        }

        ksort($candidates);

        return array_values($candidates);
    }

    /**
     * An Image's `srcset` attribute, such as `…/thumb 150w, …/card 400w`, from srcsetCandidates().
     *
     * @param  array<string|ImageStyle> | string  $set
     */
    public function srcset(Image $image, array|string $set, ?int $maxWidth = null): string
    {
        return implode(', ', array_map(
            static fn (array $candidate): string => "{$candidate['url']} {$candidate['width']}w",
            $this->srcsetCandidates($image, $set, $maxWidth),
        ));
    }

    /**
     * The width and height of an Image's Variant in this style: the Variant's own once it exists, otherwise the
     * style's declared width, with its declared height or the Image's aspect ratio. Null when the style declares no
     * width and the Variant doesn't exist yet.
     *
     * @return array{width: int, height: int} | null
     */
    public function dimensions(Image $image, string|ImageStyle $style): ?array
    {
        $style = $this->style($style);
        $variant = $image->variant($style::name());

        if ($variant !== null) {
            return ['width' => $variant->width, 'height' => $variant->height];
        }

        $width = $style->width();

        if ($width === null || $image->width < 1) {
            return null;
        }

        return ['width' => $width, 'height' => $style->height() ?? (int) round($width * $image->height / $image->width)];
    }

    /**
     * The URL its disk serves a Variant at: a temporary one when it's private.
     */
    public function variantUrl(Variant $variant, Visibility $visibility): string
    {
        if ($visibility === Visibility::Public) {
            return Storage::disk($variant->disk)->url($variant->path);
        }

        return $this->ensureTemporaryUrls($variant->disk)->temporaryUrl($variant->path, $this->expiration());
    }

    /**
     * The disk new originals or Variants of an Image with this Visibility are written to.
     *
     * @param  'originals' | 'variants'  $kind
     */
    public function diskFor(Visibility $visibility, string $kind): string
    {
        return config("image-cache.disks.{$visibility->value}.{$kind}", $visibility === Visibility::Public ? 'public' : 'local');
    }

    /**
     * Where an Image's Variant in this style is stored: {prefix}/{style}/{original path}, with the extension of the
     * Variant's format when it differs from the original's.
     */
    public function variantPath(Image $image, string $style, Format $format): string
    {
        $path = $image->path;

        if (Format::tryCreate($image->mime_type) !== $format) {
            $path = Str::beforeLast($path, '.').'.'.$format->fileExtension()->value;
        }

        return trim(config('image-cache.variants_prefix', 'image-cache'), '/').'/'.$style.'/'.$path;
    }

    protected function ensureTemporaryUrls(string $disk): FilesystemAdapter|Filesystem
    {
        $filesystem = Storage::disk($disk);

        if (! $filesystem instanceof FilesystemAdapter || ! $filesystem->providesTemporaryUrls()) {
            throw new TemporaryUrlsNotSupportedException("The disk [{$disk}] can't make temporary URLs, which private Images need: use `s3`, or turn on `serve` for a `local` disk.");
        }

        return $filesystem;
    }

    protected function expiration(): DateTimeInterface
    {
        return now()->addMinutes((int) config('image-cache.private.url_ttl', 5));
    }

    protected function nameFor(string|ImageStyle $style): string
    {
        if ($style instanceof ImageStyle) {
            return $style::name();
        }

        if (is_a($style, ImageStyle::class, true)) {
            return $style::name();
        }

        return $style;
    }
}
