<?php

declare(strict_types=1);

namespace GpxToolkit\Services\File;

interface FileInterface
{
    public function getPath(): string;
    public function getFilename(): string;
    public function getExtension(): string;
    public function getSize(): int;
    public function getMimeType(): string;
    public function getContent(): string;
    public function exists(): bool;
    public static function create(string $path): self;
}
