<?php

declare(strict_types=1);

namespace App\Tests;

use App\CardTrader\PriceStats;
use PHPUnit\Framework\TestCase;

class PriceStatsTest extends TestCase
{
    public function testEmptyList(): void
    {
        $stats = PriceStats::fromCents([]);

        $this->assertSame(0, $stats->count);
        $this->assertNull($stats->minCents);
        $this->assertNull($stats->avgCents);
    }

    public function testOddCount(): void
    {
        $stats = PriceStats::fromCents([300, 100, 200]);

        $this->assertSame(3, $stats->count);
        $this->assertSame(100, $stats->minCents);
        $this->assertSame(300, $stats->maxCents);
        $this->assertSame(200.0, $stats->avgCents);
        $this->assertSame(200, $stats->medianCents);
    }

    public function testEvenCount(): void
    {
        $stats = PriceStats::fromCents([100, 400, 200, 300]);

        $this->assertSame(4, $stats->count);
        $this->assertSame(250, $stats->medianCents);
    }
}
