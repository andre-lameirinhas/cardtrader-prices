<?php

declare(strict_types=1);

namespace App\Tests\CardTrader;

use App\CardTrader\Listing;
use App\CardTrader\ListingStats;
use PHPUnit\Framework\TestCase;

class ListingStatsTest extends TestCase
{
    private static function listing(int $priceCents = 1000, bool $reverseHolo = false, string $condition = 'Near Mint'): Listing
    {
        return new Listing($priceCents, 'EUR', 'Milotic', '012/101', 'EX Hidden Legends', 'Holo Rare', $reverseHolo, $condition);
    }

    public function testGroupByVariantSplitsRegularAndReverseHolo(): void
    {
        $regular = self::listing(reverseHolo: false);
        $reverse = self::listing(reverseHolo: true);

        $groups = ListingStats::groupByVariant([$regular, $reverse]);

        $this->assertSame(['Regular', 'Reverse Holo'], array_keys($groups));
        $this->assertSame([$regular], $groups['Regular']);
        $this->assertSame([$reverse], $groups['Reverse Holo']);
    }

    public function testByConditionOrdersStandardConditionsFirst(): void
    {
        $stats = ListingStats::byCondition([
            self::listing(1000, condition: 'Played'),
            self::listing(2000, condition: 'Near Mint'),
        ]);

        $this->assertSame(['Near Mint', 'Slightly Played', 'Moderately Played', 'Played', 'Poor', 'ALL'], array_keys($stats));
    }

    public function testByConditionGivesAZeroCountEntryForAStandardConditionWithNoListings(): void
    {
        $stats = ListingStats::byCondition([self::listing(condition: 'Near Mint')]);

        $this->assertSame(0, $stats['Poor']->count);
        $this->assertNull($stats['Poor']->minCents);
    }

    public function testByConditionAppendsUnknownConditionsAfterTheStandardOrder(): void
    {
        $stats = ListingStats::byCondition([self::listing(condition: 'Damaged')]);

        $keys = array_keys($stats);
        $this->assertLessThan(array_search('ALL', $keys, true), array_search('Damaged', $keys, true));
        $this->assertSame(1, $stats['Damaged']->count);
    }

    public function testByConditionAllAggregatesEveryListing(): void
    {
        $stats = ListingStats::byCondition([
            self::listing(1000, condition: 'Near Mint'),
            self::listing(2000, condition: 'Played'),
        ]);

        $this->assertSame(2, $stats['ALL']->count);
        $this->assertSame(1000, $stats['ALL']->minCents);
        $this->assertSame(2000, $stats['ALL']->maxCents);
    }

    public function testByConditionOnEmptyListingsStillReturnsAllStandardConditionsAndAll(): void
    {
        $stats = ListingStats::byCondition([]);

        $this->assertSame(['Near Mint', 'Slightly Played', 'Moderately Played', 'Played', 'Poor', 'ALL'], array_keys($stats));
        $this->assertSame(0, $stats['ALL']->count);
    }
}
