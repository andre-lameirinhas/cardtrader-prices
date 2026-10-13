<?php

declare(strict_types=1);

namespace App\Tests\CardTrader;

use App\CardTrader\Blueprint;
use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
use App\CardTrader\Expansion;
use App\CardTrader\PokemonCatalog;
use PHPUnit\Framework\TestCase;

class PokemonCatalogTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function single(int $id, ?string $number): array
    {
        return [
            'id' => $id,
            'category_id' => 73,
            'fixed_properties' => $number === null ? [] : ['collector_number' => $number],
        ];
    }

    private function catalogWithBlueprints(): PokemonCatalog
    {
        $client = $this->createMock(Client::class);
        $client->method('getBlueprints')->with(4195)->willReturn([
            ['id' => 331810, 'category_id' => 68, 'fixed_properties' => []],
            self::single(342228, '001'),
            self::single(342359, '087'),
            self::single(342360, '087'),
            self::single(999999, null),
            self::single(888888, ''),
        ]);

        return new PokemonCatalog($client);
    }

    public function testExpansionsKeepsOnlyPokemon(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getExpansions')->willReturn([
            ['id' => 1, 'game_id' => 1, 'name' => 'Alpha'],
            ['id' => 4195, 'game_id' => 5, 'name' => 'Black Bolt'],
            ['id' => 2, 'name' => 'No game'],
            ['id' => 4266, 'game_id' => 5, 'name' => 'Black Bolt - Poké Ball Reverse Holo'],
        ]);

        $this->assertSame([4195, 4266], array_map(static fn (Expansion $e) => $e->id, (new PokemonCatalog($client))->expansions()));
    }

    public function testBlueprintsReturnsEverything(): void
    {
        $this->assertSame([331810, 342228, 342359, 342360, 999999, 888888], self::ids($this->catalogWithBlueprints()->blueprints(4195)));
    }

    public function testSinglesDropsSealedProduct(): void
    {
        $this->assertSame([342228, 342359, 342360, 999999, 888888], self::ids($this->catalogWithBlueprints()->singles(4195)));
    }

    public function testSinglesByNumberGroupsByCollectorNumberAndSkipsSinglesWithoutOne(): void
    {
        $singles = $this->catalogWithBlueprints()->singlesByNumber(4195);

        $this->assertSame(['001', '087'], array_keys($singles));
        $this->assertSame([342228], self::ids($singles['001']));
        $this->assertSame([342359, 342360], self::ids($singles['087']));
    }

    /**
     * @param list<Blueprint> $blueprints
     * @return list<int>
     */
    private static function ids(array $blueprints): array
    {
        return array_map(static fn (Blueprint $b) => $b->id, $blueprints);
    }

    /**
     * @param list<int> $siblingIds
     */
    private function clientWithExpansions(array $siblingIds = [4266, 4263]): Client
    {
        $names = [4266 => 'Black Bolt - Poké Ball Reverse Holo', 4263 => 'Black Bolt - Master Ball Reverse Holo'];
        $expansions = [['id' => 4195, 'game_id' => 5, 'name' => 'Black Bolt']];
        foreach ($siblingIds as $id) {
            $expansions[] = ['id' => $id, 'game_id' => 5, 'name' => $names[$id]];
        }

        $client = $this->createMock(Client::class);
        $client->method('getExpansions')->willReturn($expansions);

        return $client;
    }

    public function testExpansionWithSiblingsFindsBothSiblingsByName(): void
    {
        $resolved = (new PokemonCatalog($this->clientWithExpansions()))->expansionWithSiblings(4195);

        $this->assertSame(['main' => 4195, 'Poké Ball Reverse Holo' => 4266, 'Master Ball Reverse Holo' => 4263], $resolved);
    }

    public function testExpansionWithSiblingsLeavesAMissingSiblingAsNull(): void
    {
        $resolved = (new PokemonCatalog($this->clientWithExpansions([4263])))->expansionWithSiblings(4195);

        $this->assertNotNull($resolved);
        $this->assertNull($resolved['Poké Ball Reverse Holo']);
        $this->assertSame(4263, $resolved['Master Ball Reverse Holo']);
    }

    public function testExpansionWithSiblingsReturnsNullForUnknownMainId(): void
    {
        $this->assertNull((new PokemonCatalog($this->clientWithExpansions()))->expansionWithSiblings(9999));
    }

    public function testClientErrorsPropagate(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getExpansions')->willThrowException(new CardTraderException('boom'));

        $this->expectException(CardTraderException::class);

        (new PokemonCatalog($client))->expansions();
    }
}
