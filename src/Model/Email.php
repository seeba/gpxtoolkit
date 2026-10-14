<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * GPX 1.1 emailType: the address is split into id and domain.
 */
final readonly class Email
{
    public function __construct(
        public string $id,
        public string $domain,
    ) {
    }

    public function address(): string
    {
        return $this->id.'@'.$this->domain;
    }
}
