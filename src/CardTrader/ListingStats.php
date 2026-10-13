<?php

declare(strict_types=1);

namespace App\CardTrader;

/**
 * Shapes marketplace listings for display: split by variant, then price stats per condition.
 */
final class ListingStats
{
    /** @var list<string> */
    private const CONDITION_ORDER = ['Near Mint', 'Slightly Played', 'Moderately Played', 'Played', 'Poor'];

    /**
     * Listings split into "Regular" and "Reverse Holo".
     *
     * @param list<Listing> $listings
     * @return array<string, list<Listing>>
     */
    public static function groupByVariant(array $listings): array
    {
        $groups = [];
        foreach ($listings as $listing) {
            $groups[$listing->reverseHolo ? 'Reverse Holo' : 'Regular'][] = $listing;
        }

        return $groups;
    }

    /**
     * PriceStats per condition, in display order: CONDITION_ORDER first (a condition with no
     * listings still gets a zero-count entry), then any other conditions, then an "ALL" entry.
     *
     * @param list<Listing> $listings
     * @return array<string, PriceStats>
     */
    public static function byCondition(array $listings): array
    {
        $byCondition = [];
        foreach ($listings as $listing) {
            $byCondition[$listing->condition][] = $listing->priceCents;
        }

        $extraConditions = array_diff(array_keys($byCondition), self::CONDITION_ORDER);
        $orderedConditions = [...self::CONDITION_ORDER, ...$extraConditions];

        $stats = [];
        foreach ($orderedConditions as $condition) {
            $stats[$condition] = PriceStats::fromCents($byCondition[$condition] ?? []);
        }

        $stats['ALL'] = PriceStats::fromCents(array_map(static fn (Listing $l) => $l->priceCents, $listings));

        return $stats;
    }
}
