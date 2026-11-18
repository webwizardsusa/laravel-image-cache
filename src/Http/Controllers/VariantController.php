<?php

namespace Webwizardsusa\ImageCache\Http\Controllers;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webwizardsusa\ImageCache\Enums\Visibility;
use Webwizardsusa\ImageCache\Facades\ImageCache;
use Webwizardsusa\ImageCache\Generation\VariantGenerator;

/**
 * Generates a missing Variant and redirects to the URL its disk serves it at. Private Images need a valid signature,
 * which is the only check: whoever was given the URL may see the Image until it expires. When another request holds
 * the lock for longer than `image-cache.lock.timeout`, answers 503 so the client retries.
 */
class VariantController
{
    public function __invoke(Request $request, string $style, string $image, VariantGenerator $generator): RedirectResponse|Response
    {
        abort_unless(ImageCache::has($style), 404);

        $image = ImageCache::newImageQuery()->where('ulid', $image)->firstOrFail();

        abort_if($image->visibility === Visibility::Private && ! $request->hasValidSignature(), 403);

        try {
            $variant = $generator->ensure($image, $style);
        } catch (LockTimeoutException) {
            return response('', 503, ['Retry-After' => (string) max(1, (int) config('image-cache.lock.timeout', 10))]);
        }

        return redirect()->away(ImageCache::variantUrl($variant, $image->visibility));
    }
}
