<?php

declare(strict_types=1);

namespace GpxToolkit\Exception;

use RuntimeException;
use Throwable;

/**
 * The GPX source (file, stream) could not be read.
 */
final class ReadException extends RuntimeException implements GpxToolkitException
{
    public function __construct(
        string $message,
        public readonly string $source,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('GPX file "%s" does not exist.', $path), $path);
    }

    public static function notAFile(string $path): self
    {
        return new self(sprintf('GPX path "%s" is not a regular file.', $path), $path);
    }

    public static function unreadable(string $source, ?string $reason = null): self
    {
        return new self(
            sprintf('GPX source "%s" could not be read%s.', $source, null === $reason ? '' : ': '.$reason),
            $source,
        );
    }
}
