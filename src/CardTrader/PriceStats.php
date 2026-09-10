<?php

declare(strict_types=1);

namespace App\CardTrader;

final class PriceStats
{
    public function __construct(
        public readonly int $count,
        public readonly ?int $minCents,
        public readonly ?int $maxCents,
        public readonly ?float $avgCents,
        public readonly ?int $medianCents,
    ) {
    }

    /**
     * @param list<int> $pricesCents
     */
    public static function fromCents(array $pricesCents): self
    {
        $count = count($pricesCents);

        if ($count === 0) {
            return new self(0, null, null, null, null);
        }

        sort($pricesCents);
        $mid = intdiv($count, 2);
        $median = $count % 2 === 0
            ? intdiv($pricesCents[$mid - 1] + $pricesCents[$mid], 2)
            : $pricesCents[$mid];

        return new self(
            count: $count,
            minCents: $pricesCents[0],
            maxCents: $pricesCents[$count - 1],
            avgCents: array_sum($pricesCents) / $count,
            medianCents: $median,
        );
    }
}
