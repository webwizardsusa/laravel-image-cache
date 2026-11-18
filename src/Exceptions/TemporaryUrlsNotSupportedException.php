<?php

namespace Webwizardsusa\ImageCache\Exceptions;

use RuntimeException;

/**
 * Thrown when a private Image's disk can't make temporary URLs: use `s3`, or turn on `serve` for a `local` disk.
 */
class TemporaryUrlsNotSupportedException extends RuntimeException {}
