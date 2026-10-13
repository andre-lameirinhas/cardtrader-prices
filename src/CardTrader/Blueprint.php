<?php

declare(strict_types=1);

namespace App\CardTrader;

/**
 * A blueprint: a specific card/print, or a sealed product/accessory.
 */
final class Blueprint
{
    /**
     * @param list<string> $editableProperties
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $categoryId,
        public readonly ?string $collectorNumber,
        public readonly ?string $rarity,
        private readonly array $editableProperties = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $fixed = $data['fixed_properties'] ?? [];
        if (!is_array($fixed)) {
            $fixed = [];
        }

        $editable = $data['editable_properties'] ?? [];
        if (!is_array($editable)) {
            $editable = [];
        }

        $number = (string) ($fixed['collector_number'] ?? '');

        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            collectorNumber: $number === '' ? null : $number,
            rarity: isset($fixed['pokemon_rarity']) ? (string) $fixed['pokemon_rarity'] : null,
            editableProperties: array_map(
                static fn (mixed $property) => is_array($property) ? (string) ($property['name'] ?? '') : '',
                array_values($editable),
            ),
        );
    }

    public function supportsReverse(): bool
    {
        return in_array('pokemon_reverse', $this->editableProperties, true);
    }
}
