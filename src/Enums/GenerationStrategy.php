<?php

namespace Webwizardsusa\ImageCache\Enums;

/**
 * When an Image Style's Variants are generated: the first time one is requested (OnDemand), while the Image is being
 * stored (OnCreate), or on the queue right after it's stored (Queued). Whatever the strategy, a missing Variant is
 * generated when it's requested.
 */
enum GenerationStrategy
{
    case OnDemand;
    case OnCreate;
    case Queued;
}
