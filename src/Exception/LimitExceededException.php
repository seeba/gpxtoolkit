<?php

declare(strict_types=1);

namespace GpxToolkit\Exception;

use RuntimeException;

/**
 * The source exceeds a limit configured in ToolkitOptions.
 */
final class LimitExceededException extends RuntimeException implements GpxToolkitException
{
    public function __construct(
        string $message,
        public readonly string $source,
        public readonly int $limit,
    ) {
        parent::__construct($message);
    }

    public static function size(string $source, int $maxBytes): self
    {
        return new self(sprintf('GPX source "%s" exceeds the size limit of %d bytes.', $source, $maxBytes), $source, $maxBytes);
    }

    public static function points(string $source, int $maxPoints, int $actual): self
    {
        return new self(
            sprintf('GPX source "%s" contains %d points, the limit is %d.', $source, $actual, $maxPoints),
            $source,
            $maxPoints,
        );
    }
}
