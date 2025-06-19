<?php

declare(strict_types=1);

namespace GpxToolkit\Services\File;

class GpxFile implements FileInterface
{
    private function __construct(
        private string $path
    ) {
    }

    public static function create(string $path): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File does not exist: " . $path);
        }

        return new self($path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFilename(): string
    {
        return basename($this->path);
    }

    public function getExtension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function getSize(): int
    {
        return filesize($this->path);
    }

    public function getMimeType(): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->path);
        finfo_close($finfo);
        return $mimeType;
    }

    public function getContent(): string
    {
        return file_get_contents($this->path);
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }
}
