<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
use App\CardTrader\PokemonCatalog;
use App\Command\MapCardsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class MapCardsCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/map-cards-' . uniqid();
        mkdir($this->dir);

        $this->writeCard('001.001-086.card.json', '49946', '001/086', 'Snivy', [
            '95805' => 'Normal',
            '95806' => 'Reverse Holo',
            '95807' => 'Poké Ball Reverse Holo',
            '95808' => 'Master Ball Reverse Holo',
            '96627' => 'Tinsel Holo',
        ]);
        $this->writeCard('087.087-086.card.json', '50032', '087/086', 'Snivy', ['95999' => 'Normal Holo']);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->dir}/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /**
     * @param array<int|string, string> $variants variant ID => type
     */
    private function writeCard(string $file, string $id, string $number, string $name, array $variants): void
    {
        $card = [
            'id' => $id,
            'name' => $name,
            'number' => $number,
            'variants' => array_map(
                static fn (int|string $variantId, string $type) => ['id' => (string) $variantId, 'type' => $type],
                array_keys($variants),
                $variants,
            ),
        ];
        file_put_contents("{$this->dir}/{$file}", json_encode($card));
    }

    /**
     * @return array<string, mixed>
     */
    private static function single(int $id, string $number, string $name, bool $reversible = false): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'category_id' => 73,
            'fixed_properties' => ['collector_number' => $number],
            'editable_properties' => $reversible ? [['name' => 'pokemon_reverse']] : [],
        ];
    }

    /**
     * @param list<int> $siblingIds
     */
    private function client(array $siblingIds = [4266, 4263]): Client
    {
        $names = [4266 => 'Black Bolt - Poké Ball Reverse Holo', 4263 => 'Black Bolt - Master Ball Reverse Holo'];
        $expansions = [
            ['id' => 4195, 'game_id' => 5, 'name' => 'Black Bolt'],
            ['id' => 4188, 'game_id' => 5, 'name' => 'Black Bolt | sv11B'],
        ];
        foreach ($siblingIds as $id) {
            $expansions[] = ['id' => $id, 'game_id' => 5, 'name' => $names[$id]];
        }

        $client = $this->createMock(Client::class);
        $client->method('getExpansions')->willReturn($expansions);
        $client->method('getBlueprints')->willReturnCallback(static fn (int $expansionId) => match ($expansionId) {
            4195 => [
                self::single(342228, '001', 'Snivy', reversible: true),
                self::single(342359, '087', 'Snivy'),
                ['id' => 331810, 'name' => 'Elite Trainer Box', 'category_id' => 68, 'fixed_properties' => []],
            ],
            4266 => [self::single(343347, '001', 'Snivy')],
            4263 => [self::single(343427, '001', 'Snivy')],
            default => [],
        });

        return $client;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: list<array<string, mixed>>, 1: string, 2: int}
     */
    private function execute(Client $client, array $input = []): array
    {
        $tester = new CommandTester(new MapCardsCommand(new PokemonCatalog($client)));
        $exitCode = $tester->execute(['dir' => $this->dir, 'expansion-id' => '4195', ...$input], ['capture_stderr_separately' => true]);

        /** @var list<array<string, mixed>> $mapping */
        $mapping = json_decode($tester->getDisplay(), true) ?? [];

        return [$mapping, $tester->getErrorOutput(), $exitCode];
    }

    /**
     * @param array<string, mixed> $card
     * @return array<string, mixed>
     */
    private static function variant(array $card, string $variantId): array
    {
        /** @var list<array<string, mixed>> $variants */
        $variants = $card['variants'];
        foreach ($variants as $variant) {
            if ($variant['variant_id'] === $variantId) {
                return $variant;
            }
        }
        self::fail("Variant {$variantId} not in mapping");
    }

    public function testMapsEveryVariantOfACard(): void
    {
        [$mapping, $stderr, $exitCode] = $this->execute($this->client(), ['--number' => ['001']]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertCount(1, $mapping);
        $this->assertSame('49946', $mapping[0]['card_id']);
        $this->assertSame(342228, $mapping[0]['blueprint_id']);

        $this->assertSame(
            ['variant_id' => '95805', 'type' => 'Normal', 'blueprint_id' => 342228, 'expansion_id' => 4195, 'reverse' => false],
            self::variant($mapping[0], '95805'),
        );
        $this->assertSame(342228, self::variant($mapping[0], '95806')['blueprint_id']);
        $this->assertTrue(self::variant($mapping[0], '95806')['reverse']);
        $this->assertSame(343347, self::variant($mapping[0], '95807')['blueprint_id']);
        $this->assertSame(4266, self::variant($mapping[0], '95807')['expansion_id']);
        $this->assertSame(343427, self::variant($mapping[0], '95808')['blueprint_id']);
        $this->assertSame(4263, self::variant($mapping[0], '95808')['expansion_id']);
        $this->assertNull(self::variant($mapping[0], '96627')['blueprint_id']);
        $this->assertStringContainsString('4 variants matched, 1 unmatched', $stderr);
        $this->assertStringContainsString('Tinsel Holo', $stderr);
    }

    public function testMapsAllCardsWithoutNumberFilter(): void
    {
        [$mapping] = $this->execute($this->client());

        $this->assertSame(['001/086', '087/086'], array_column($mapping, 'number'));
        $this->assertSame(342359, self::variant($mapping[1], '95999')['blueprint_id']);
    }

    public function testNumberFilterIgnoresLeadingZeros(): void
    {
        [$mapping] = $this->execute($this->client(), ['--number' => ['1', '87']]);

        $this->assertSame(['001/086', '087/086'], array_column($mapping, 'number'));
    }

    public function testWarnsAboutNumbersWithNoCardFile(): void
    {
        [$mapping, $stderr] = $this->execute($this->client(), ['--number' => ['1', '999']]);

        $this->assertSame(['001/086'], array_column($mapping, 'number'));
        $this->assertStringContainsString('No card file for --number 999', $stderr);
        $this->assertStringNotContainsString('--number 1 ', $stderr);
    }

    public function testReverseHoloIsUnmatchedWhenBlueprintHasNoReverseOption(): void
    {
        $this->writeCard('087.087-086.card.json', '50032', '087/086', 'Snivy', ['96000' => 'Reverse Holo']);

        [$mapping] = $this->execute($this->client(), ['--number' => ['087']]);

        $variant = self::variant($mapping[0], '96000');
        $this->assertNull($variant['blueprint_id']);
        $this->assertStringContainsString('no reverse option', (string) $variant['reason']);
    }

    public function testCardMissingFromExpansionIsUnmatched(): void
    {
        $this->writeCard('050.050-086.card.json', '50000', '050/086', 'Mystery', ['1' => 'Normal']);

        [$mapping, $stderr] = $this->execute($this->client(), ['--number' => ['050']]);

        $this->assertNull($mapping[0]['blueprint_id']);
        $this->assertStringContainsString('1 cards (1 without a base blueprint)', $stderr);
    }

    public function testMissingSiblingExpansionLeavesThoseVariantsUnmatched(): void
    {
        [$mapping, $stderr] = $this->execute($this->client([4263]), ['--number' => ['001']]);

        $this->assertNull(self::variant($mapping[0], '95807')['blueprint_id']);
        $this->assertSame(343427, self::variant($mapping[0], '95808')['blueprint_id']);
        $this->assertStringContainsString('No "Poké Ball Reverse Holo" expansion found', $stderr);
    }

    public function testSkipsUnreadableCardFiles(): void
    {
        file_put_contents("{$this->dir}/050.050-086.card.json", '{not json');

        [$mapping, $stderr, $exitCode] = $this->execute($this->client());

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(['001/086', '087/086'], array_column($mapping, 'number'));
        $this->assertStringContainsString('Skipping unreadable card file 050.050-086.card.json', $stderr);
    }

    public function testWarnsOnNameMismatch(): void
    {
        $this->writeCard('001.001-086.card.json', '49946', '001/086', 'Snivyy', ['95805' => 'Normal']);

        [, $stderr] = $this->execute($this->client(), ['--number' => ['001']]);

        $this->assertStringContainsString('local name "Snivyy" differs', $stderr);
    }

    public function testWritesMappingToOutputFile(): void
    {
        $path = "{$this->dir}/out.json";

        [, $stderr, $exitCode] = $this->execute($this->client(), ['--number' => ['001'], '--output' => $path]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Wrote mapping', $stderr);
        $written = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($written);
        $this->assertSame('49946', $written[0]['card_id']);
    }

    public function testUnknownExpansionIdFails(): void
    {
        [, $stderr, $exitCode] = $this->execute($this->client(), ['expansion-id' => '9999']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Unknown Pokémon expansion ID 9999', $stderr);
    }

    public function testRejectsNonNumericExpansionId(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('getExpansions');

        [, $stderr, $exitCode] = $this->execute($client, ['expansion-id' => 'blk']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid expansion ID "blk"', $stderr);
    }

    public function testReportsClientErrorsAsFailure(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getExpansions')->willThrowException(new CardTraderException('boom'));

        [, $stderr, $exitCode] = $this->execute($client);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('boom', $stderr);
    }
}
