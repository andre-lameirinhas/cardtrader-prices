<?php

declare(strict_types=1);

namespace App\Mapping;

use App\Card\Card;
use App\Card\CardVariant;
use App\CardTrader\PokemonCatalog;

/**
 * Matches a card's variants to CardTrader blueprints by collector number: Normal/Normal Holo and
 * Reverse Holo map to the main expansion's blueprint; Poké Ball / Master Ball variants map to
 * their sibling expansion (see PokemonCatalog::SIBLING_TYPES). Pure: no IO, no API calls.
 */
final class VariantMatcher
{
    /** @var array<string, array<string, array<string, mixed>>> key => collector number => the one blueprint */
    private array $blueprints = [];

    /** @var array<string, array<string, list<int>>> key => collector number => blueprint IDs sharing it */
    private array $ambiguous = [];

    /**
     * @param array<string, int|null> $expansions main plus sibling expansion IDs, from PokemonCatalog::expansionWithSiblings()
     * @param array<string, array<string, non-empty-list<array<string, mixed>>>> $singlesByNumber same keys, each from PokemonCatalog::singlesByNumber()
     */
    public function __construct(
        private readonly array $expansions,
        array $singlesByNumber,
    ) {
        foreach ($expansions as $key => $expansionId) {
            foreach ($singlesByNumber[$key] ?? [] as $number => $group) {
                if (count($group) === 1) {
                    $this->blueprints[$key][$number] = $group[0];
                } else {
                    $this->ambiguous[$key][$number] = array_map(static fn (array $b) => (int) $b['id'], $group);
                }
            }
        }
    }

    public function match(Card $card): CardMatch
    {
        $number = $card->collectorNumber();
        $base = $this->blueprints['main'][$number] ?? null;

        $warnings = [];
        foreach ($this->ambiguous as $key => $shared) {
            if (isset($shared[$number])) {
                $warnings[] = "#{$number}: " . self::sharedBy($shared[$number], (int) $this->expansions[$key]) . '; those variants are left unmatched.';
            }
        }

        if ($base !== null && strcasecmp((string) $base['name'], $card->name) !== 0) {
            $warnings[] = "#{$number}: local name \"{$card->name}\" differs from CardTrader \"{$base['name']}\" (blueprint {$base['id']}).";
        }

        $variants = array_map(
            fn (CardVariant $variant) => $this->matchVariant($variant, $number, $base),
            $card->variants,
        );

        return new CardMatch($base !== null ? (int) $base['id'] : null, $variants, $warnings);
    }

    /**
     * @param array<string, mixed>|null $base
     */
    private function matchVariant(CardVariant $variant, string $number, ?array $base): VariantMatch
    {
        $type = $variant->type;

        if (in_array($type, PokemonCatalog::SIBLING_TYPES, true)) {
            $blueprint = $this->blueprints[$type][$number] ?? null;
            if ($blueprint === null) {
                return VariantMatch::unmatched($variant, match (true) {
                    $this->expansions[$type] === null => "no {$type} expansion",
                    isset($this->ambiguous[$type][$number]) => self::sharedBy($this->ambiguous[$type][$number], $this->expansions[$type]),
                    default => "#{$number} not in expansion {$this->expansions[$type]}",
                });
            }

            return VariantMatch::matched($variant, (int) $blueprint['id'], (int) $this->expansions[$type], false);
        }

        $reverse = match ($type) {
            'Normal', 'Normal Holo' => false,
            'Reverse Holo' => true,
            default => null,
        };

        if ($reverse === null) {
            return VariantMatch::unmatched($variant, 'no mapping rule for this variant type');
        }

        if ($base === null) {
            return VariantMatch::unmatched($variant, isset($this->ambiguous['main'][$number])
                ? self::sharedBy($this->ambiguous['main'][$number], (int) $this->expansions['main'])
                : "#{$number} not in expansion {$this->expansions['main']}");
        }

        if ($reverse && !self::supportsReverse($base)) {
            return VariantMatch::unmatched($variant, "blueprint {$base['id']} has no reverse option");
        }

        return VariantMatch::matched($variant, (int) $base['id'], (int) $this->expansions['main'], $reverse);
    }

    /**
     * @param list<int> $blueprintIds
     */
    private static function sharedBy(array $blueprintIds, int $expansionId): string
    {
        return 'collector number shared by blueprints ' . implode(', ', $blueprintIds) . " in expansion {$expansionId}";
    }

    /**
     * @param array<string, mixed> $blueprint
     */
    private static function supportsReverse(array $blueprint): bool
    {
        foreach ($blueprint['editable_properties'] ?? [] as $property) {
            if (($property['name'] ?? null) === 'pokemon_reverse') {
                return true;
            }
        }

        return false;
    }
}
