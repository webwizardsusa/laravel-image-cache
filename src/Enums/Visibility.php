<?php

namespace Webwizardsusa\ImageCache\Enums;

/**
 * Whether anyone with an Image's URL can see it (Public) or only someone given a temporary signed URL (Private). An
 * Image's Variants share its Visibility.
 */
enum Visibility: string
{
    case Public = 'public';
    case Private = 'private';
}
