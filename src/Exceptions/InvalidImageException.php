<?php

namespace Webwizardsusa\ImageCache\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a file given to store() isn't an image Intervention can read.
 */
class InvalidImageException extends InvalidArgumentException {}
