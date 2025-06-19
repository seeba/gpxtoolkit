<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Parser;

use GpxToolkit\Services\File\FileInterface;
use GpxToolkit\Services\Model\WorkoutDataInterface;

interface ParserInterface
{
    public function parse(FileInterface $file): WorkoutDataInterface;
}
