<?php

declare(strict_types=1);

namespace GpxToolkit;

use InvalidArgumentException;

/**
 * Parser configuration of a single Toolkit instance.
 */
final readonly class ToolkitOptions
{
    public const int DEFAULT_MAX_BYTES = 32 * 1024 * 1024;
    public const int DEFAULT_MAX_POINTS = 300_000;

    /**
     * @param bool     $allowMissingNamespace compatibility mode: accept documents whose elements have no namespace
     *                                        and read them as GPX 1.1
     * @param int|null $maxBytes              maximum size of the source in bytes, checked before the whole content is read;
     *                                        null disables the limit
     * @param int|null $maxPoints             maximum number of wpt, rtept and trkpt elements, checked before the model is built;
     *                                        null disables the limit
     */
    public function __construct(
        public bool $allowMissingNamespace = false,
        public ?int $maxBytes = self::DEFAULT_MAX_BYTES,
        public ?int $maxPoints = self::DEFAULT_MAX_POINTS,
    ) {
        if (null !== $maxBytes && $maxBytes < 1) {
            throw new InvalidArgumentException('maxBytes must be a positive integer or null.');
        }
        if (null !== $maxPoints && $maxPoints < 1) {
            throw new InvalidArgumentException('maxPoints must be a positive integer or null.');
        }
    }
}
