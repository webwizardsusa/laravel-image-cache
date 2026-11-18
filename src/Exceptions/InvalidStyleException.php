<?php

namespace Webwizardsusa\ImageCache\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a class registered as an Image Style isn't one, or its name is taken or isn't a valid slug.
 */
class InvalidStyleException extends InvalidArgumentException {}
