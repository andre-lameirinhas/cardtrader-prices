<?php

declare(strict_types=1);

namespace App\CardTrader;

/**
 * Pokémon-specific view of the CardTrader catalog: which game and category IDs matter, and how to filter for them.
 */
class PokemonCatalog
{
    public const GAME_ID = 5;
    public const SINGLES_CATEGORY_ID = 73;

    /** Variant types that live in a sibling expansion named "<main name> - <type>". */
    public const SIBLING_TYPES = ['Poké Ball Reverse Holo', 'Master Ball Reverse Holo'];

    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Pokémon expansions only. The API ignores a `game_id` filter server-side, so this filters client-side.
     *
     * @return list<Expansion>
     */
    public function expansions(): array
    {
        return array_values(array_filter(
            array_map(Expansion::fromArray(...), $this->client->getExpansions()),
            static fn (Expansion $e) => $e->gameId === self::GAME_ID,
        ));
    }

    /**
     * Every blueprint of an expansion: singles, sealed product and accessories.
     *
     * @return list<Blueprint>
     */
    public function blueprints(int $expansionId): array
    {
        return array_map(Blueprint::fromArray(...), $this->client->getBlueprints($expansionId));
    }

    /**
     * @return list<Blueprint>
     */
    public function singles(int $expansionId): array
    {
        return array_values(array_filter(
            $this->blueprints($expansionId),
            static fn (Blueprint $b) => $b->categoryId === self::SINGLES_CATEGORY_ID,
        ));
    }

    /**
     * Singles grouped by collector number (e.g. "001"). Promo, deck and calendar sets often give several
     * singles the same number, so each group is a list. Singles without a number are skipped.
     *
     * @return array<string, non-empty-list<Blueprint>>
     */
    public function singlesByNumber(int $expansionId): array
    {
        $indexed = [];
        foreach ($this->singles($expansionId) as $single) {
            if ($single->collectorNumber !== null) {
                $indexed[$single->collectorNumber][] = $single;
            }
        }

        return $indexed;
    }

    /**
     * The main expansion plus its sibling expansions named "<main name> - <type>" (one entry per
     * SIBLING_TYPES, null when CardTrader has no such expansion), or null when the main expansion
     * ID is unknown.
     *
     * @return array<string, int|null>|null
     */
    public function expansionWithSiblings(int $mainId): ?array
    {
        $byName = [];
        $mainName = null;
        foreach ($this->expansions() as $expansion) {
            $byName[$expansion->name] = $expansion->id;
            if ($expansion->id === $mainId) {
                $mainName = $expansion->name;
            }
        }

        if ($mainName === null) {
            return null;
        }

        $resolved = ['main' => $mainId];
        foreach (self::SIBLING_TYPES as $type) {
            $resolved[$type] = $byName["{$mainName} - {$type}"] ?? null;
        }

        return $resolved;
    }
}
