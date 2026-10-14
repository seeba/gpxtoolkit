<?php

declare(strict_types=1);

namespace GpxToolkit\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * Well-formed XML or model data that does not satisfy the GPX 1.1 contract of the library.
 */
final class ValidationException extends InvalidArgumentException implements GpxToolkitException
{
    public function __construct(
        string $message,
        public readonly ?string $source = null,
        public readonly ?string $elementPath = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function inDocument(string $message, string $source, ?string $elementPath = null, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('%s (source "%s"%s)', $message, $source, null === $elementPath ? '' : ', element '.$elementPath),
            $source,
            $elementPath,
            $previous,
        );
    }
}
