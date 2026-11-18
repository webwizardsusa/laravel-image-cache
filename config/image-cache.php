<?php

use Webwizardsusa\ImageCache\Models\Image;

return [
    /*
     * The Image model. Swap in your own to add columns: it must extend Webwizardsusa\ImageCache\Models\Image.
     */
    'image_model' => Image::class,

    /*
     * The tables for Images, their Variants, and the Usages that link Images to the records using them.
     */
    'tables' => [
        'images' => 'images',
        'variants' => 'image_variants',
        'usages' => 'image_usages',
    ],

    /*
     * The Intervention Image driver: `gd`, `imagick`, `vips` (needs intervention/image-driver-vips), or the class name
     * of any Intervention\Image\Interfaces\DriverInterface.
     */
    'driver' => env('IMAGE_CACHE_DRIVER', 'gd'),

    /*
     * The application's Image Style classes. Packages register theirs with ImageCache::register().
     */
    'styles' => [],

    /*
     * The disks new originals and Variants are written to, by visibility. Each Image and Variant records the disk it
     * was written to, so changing these only affects files written from then on. Private disks must be able to make
     * temporary URLs (`s3`, or `local` with `serve` turned on).
     */
    'disks' => [
        'public' => [
            'originals' => 'public',
            'variants' => 'public',
        ],
        'private' => [
            'originals' => 'local',
            'variants' => 'local',
        ],
    ],

    /*
     * The visibility of Images stored without one: `public` or `private`.
     */
    'default_visibility' => 'public',

    /*
     * The directory originals are stored in, and the one Variants are stored under (as {prefix}/{style}/{original path}).
     */
    'originals_directory' => 'images',
    'variants_prefix' => 'image-cache',

    /*
     * The route that generates a missing Variant and redirects to it.
     */
    'route' => [
        'prefix' => 'image-cache',
        'middleware' => [],
        'name' => 'image-cache.variant',
    ],

    /*
     * The lock that keeps two requests from generating the same Variant at once: the cache store it's taken on (null
     * for the default), how many seconds a request waits for it before answering 503, and how many seconds it's held
     * at most.
     */
    'lock' => [
        'store' => null,
        'timeout' => 10,
        'ttl' => 60,
    ],

    /*
     * The queue that Variants of Queued Image Styles are generated on.
     */
    'queue' => [
        'connection' => null,
        'queue' => null,
    ],

    /*
     * How many minutes the temporary URLs of private Images last.
     */
    'private' => [
        'url_ttl' => 5,
    ],

    /*
     * How many hours an Image stays Orphaned before image-cache:prune deletes it.
     */
    'prune' => [
        'grace_hours' => 24,
    ],
];
