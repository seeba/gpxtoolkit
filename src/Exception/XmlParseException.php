<?php

declare(strict_types=1);

namespace GpxToolkit\Exception;

use RuntimeException;

/**
 * The source is not well-formed XML or violates the XML processing policy.
 */
final class XmlParseException extends RuntimeException implements GpxToolkitException
{
    public function __construct(
        string $message,
        public readonly string $source,
        public readonly ?int $xmlLine = null,
    ) {
        parent::__construct($message);
    }

    public static function malformed(string $source, string $reason, ?int $line): self
    {
        return new self(
            sprintf('Invalid XML in GPX source "%s"%s: %s', $source, null === $line ? '' : ' at line '.$line, $reason),
            $source,
            $line,
        );
    }
}
