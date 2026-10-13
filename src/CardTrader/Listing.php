<?php

declare(strict_types=1);

namespace App\CardTrader;

/**
 * A live marketplace listing for a blueprint.
 */
final class Listing
{
    public function __construct(
        public readonly int $priceCents,
        public readonly string $priceCurrency,
        public readonly string $nameEn,
        public readonly ?string $collectorNumber,
        public readonly ?string $expansionNameEn,
        public readonly ?string $rarity,
        public readonly bool $reverseHolo,
        public readonly string $condition,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $props = $data['properties_hash'] ?? [];
        if (!is_array($props)) {
            $props = [];
        }

        $expansion = $data['expansion'] ?? [];
        if (!is_array($expansion)) {
            $expansion = [];
        }

        return new self(
            priceCents: (int) ($data['price_cents'] ?? 0),
            priceCurrency: (string) ($data['price_currency'] ?? '?'),
            nameEn: (string) ($data['name_en'] ?? '?'),
            collectorNumber: isset($props['collector_number']) ? (string) $props['collector_number'] : null,
            expansionNameEn: isset($expansion['name_en']) ? (string) $expansion['name_en'] : null,
            rarity: isset($props['pokemon_rarity']) ? (string) $props['pokemon_rarity'] : null,
            reverseHolo: ($props['pokemon_reverse'] ?? false) === true,
            condition: (string) ($props['condition'] ?? 'Unknown'),
        );
    }
}
