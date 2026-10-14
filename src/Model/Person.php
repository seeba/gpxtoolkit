<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * GPX 1.1 personType.
 */
final readonly class Person
{
    public function __construct(
        public ?string $name = null,
        public ?Email $email = null,
        public ?Link $link = null,
    ) {
    }
}
