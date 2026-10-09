<?php

declare(strict_types=1);

namespace App\Tests\CardTrader;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
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

        $this->assertSame([4195, 4266], array_column((new PokemonCatalog($client))->expansions(), 'id'));
    }

    public function testBlueprintsReturnsEverything(): void
    {
        $this->assertSame([331810, 342228, 342359, 342360, 999999, 888888], array_column($this->catalogWithBlueprints()->blueprints(4195), 'id'));
    }

    public function testSinglesDropsSealedProduct(): void
    {
        $this->assertSame([342228, 342359, 342360, 999999, 888888], array_column($this->catalogWithBlueprints()->singles(4195), 'id'));
    }

    public function testSinglesByNumberGroupsByCollectorNumberAndSkipsSinglesWithoutOne(): void
    {
        $singles = $this->catalogWithBlueprints()->singlesByNumber(4195);

        $this->assertSame(['001', '087'], array_keys($singles));
        $this->assertSame([342228], array_column($singles['001'], 'id'));
        $this->assertSame([342359, 342360], array_column($singles['087'], 'id'));
    }

    public function testClientErrorsPropagate(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getExpansions')->willThrowException(new CardTraderException('boom'));

        $this->expectException(CardTraderException::class);

        (new PokemonCatalog($client))->expansions();
    }
}
