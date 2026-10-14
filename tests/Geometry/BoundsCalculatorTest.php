<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Geometry;

use GpxToolkit\Geometry\BoundsCalculator;
use GpxToolkit\Geometry\Scope;
use GpxToolkit\Model\Bounds;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BoundsCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_bounds_of_track_points(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('loop.gpx'));

        self::assertEquals(new Bounds(50.0, 19.0, 50.01, 19.01), (new BoundsCalculator())->calculate($document));
    }

    #[Test]
    public function it_calculates_bounds_for_selected_scope(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('route.gpx'));
        $calculator = new BoundsCalculator();

        self::assertNull($calculator->calculate($document));
        self::assertEquals(new Bounds(50.0, 19.0, 50.01, 19.01), $calculator->calculate($document, Scope::Routes));
    }

    #[Test]
    public function it_returns_null_without_points(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('waypoints-only.gpx'));

        self::assertNull((new BoundsCalculator())->calculate($document));
        self::assertEquals(
            new Bounds(50.054, 19.9354, 50.0614, 19.9366),
            (new BoundsCalculator())->fromPoints($document->waypoints),
        );
    }
}
