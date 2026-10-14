<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Analysis;

use GpxToolkit\Analysis\PaceFormatter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaceFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_pace_and_rounds_across_minute_boundary(): void
    {
        $formatter = new PaceFormatter();

        self::assertSame('5:30', $formatter->format(330.0));
        self::assertSame('6:00', $formatter->format(359.6));
        self::assertNull($formatter->format(null));
        self::assertNull($formatter->format(0.0));
        self::assertNull($formatter->format(INF));
    }
}
