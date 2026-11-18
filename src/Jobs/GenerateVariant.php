<?php

namespace Webwizardsusa\ImageCache\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Webwizardsusa\ImageCache\Generation\VariantGenerator;
use Webwizardsusa\ImageCache\Models\Image;

/**
 * Generates one Image's Variant in one Image Style on the queue set in `image-cache.queue`, unless a request has
 * generated it first. With `$regenerate`, generates it again even when it exists. Only one job per Image and style is
 * queued at a time.
 */
class GenerateVariant implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Image $image, public string $style, public bool $regenerate = false)
    {
        $this->onConnection(config('image-cache.queue.connection'));
        $this->onQueue(config('image-cache.queue.queue'));
    }

    public function uniqueId(): string
    {
        return $this->image->getKey().':'.$this->style;
    }

    public function handle(VariantGenerator $generator): void
    {
        if ($this->regenerate) {
            $generator->generate($this->image, $this->style);

            return;
        }

        $generator->ensure($this->image, $this->style);
    }
}
