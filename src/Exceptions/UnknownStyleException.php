<?php

namespace Webwizardsusa\ImageCache\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when no Image Style is registered with the given name.
 */
class UnknownStyleException extends InvalidArgumentException {}
