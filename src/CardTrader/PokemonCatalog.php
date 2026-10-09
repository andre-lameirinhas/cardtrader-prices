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

    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Pokémon expansions only. The API ignores a `game_id` filter server-side, so this filters client-side.
     *
     * @return list<array<string, mixed>>
     */
    public function expansions(): array
    {
        return array_values(array_filter(
            $this->client->getExpansions(),
            static fn (array $e) => ($e['game_id'] ?? null) === self::GAME_ID,
        ));
    }

    /**
     * Every blueprint of an expansion: singles, sealed product and accessories.
     *
     * @return list<array<string, mixed>>
     */
    public function blueprints(int $expansionId): array
    {
        return $this->client->getBlueprints($expansionId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function singles(int $expansionId): array
    {
        return array_values(array_filter(
            $this->blueprints($expansionId),
            static fn (array $b) => ($b['category_id'] ?? null) === self::SINGLES_CATEGORY_ID,
        ));
    }

    /**
     * Singles grouped by collector number (e.g. "001"). Promo, deck and calendar sets often give several
     * singles the same number, so each group is a list. Singles without a number (missing or "") are skipped.
     *
     * @return array<string, non-empty-list<array<string, mixed>>>
     */
    public function singlesByNumber(int $expansionId): array
    {
        $indexed = [];
        foreach ($this->singles($expansionId) as $single) {
            $number = (string) ($single['fixed_properties']['collector_number'] ?? '');
            if ($number !== '') {
                $indexed[$number][] = $single;
            }
        }

        return $indexed;
    }
}
