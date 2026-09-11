<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
use App\Command\PriceStatsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PriceStatsCommandTest extends TestCase
{
    private function tester(Client $client): CommandTester
    {
        return new CommandTester(new PriceStatsCommand($client));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function listing(array $overrides = []): array
    {
        return array_merge([
            'name_en' => 'Milotic',
            'expansion' => ['name_en' => 'EX Hidden Legends'],
            'properties_hash' => [
                'collector_number' => '012/101',
                'pokemon_rarity' => 'Holo Rare',
                'pokemon_reverse' => false,
                'condition' => 'Near Mint',
            ],
            'price_cents' => 1000,
            'price_currency' => 'EUR',
        ], $overrides);
    }

    public function testRejectsInvalidLanguage(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('getMarketplaceListings');

        $tester = $this->tester($client);
        $exitCode = $tester->execute(['blueprint-id' => '111151', '--language' => 'zz']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid language "zz"', $tester->getDisplay());
        $this->assertStringContainsString('de, en, es, fr, it, jp, pt', $tester->getDisplay());
    }

    public function testDefaultsLanguageToEnglish(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getMarketplaceListings')
            ->with(111151, ['language' => 'en'])
            ->willReturn([self::listing()]);

        $tester = $this->tester($client);
        $exitCode = $tester->execute(['blueprint-id' => '111151']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Language: en', $tester->getDisplay());
    }

    public function testAcceptsShortLanguageOption(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getMarketplaceListings')
            ->with(111151, ['language' => 'fr'])
            ->willReturn([self::listing()]);

        $tester = $this->tester($client);
        $tester->execute(['blueprint-id' => '111151', '-l' => 'fr']);

        $this->assertStringContainsString('Language: fr', $tester->getDisplay());
    }

    public function testWarnsWhenNoListingsFound(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getMarketplaceListings')->willReturn([]);

        $tester = $this->tester($client);
        $exitCode = $tester->execute(['blueprint-id' => '111151']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No listings found', $tester->getDisplay());
    }

    public function testReportsClientErrorsAsFailure(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getMarketplaceListings')->willThrowException(new CardTraderException('boom'));

        $tester = $this->tester($client);
        $exitCode = $tester->execute(['blueprint-id' => '111151']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('boom', $tester->getDisplay());
    }

    public function testTitleIncludesCollectorNumberAndRarity(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getMarketplaceListings')->willReturn([self::listing()]);

        $tester = $this->tester($client);
        $tester->execute(['blueprint-id' => '111151']);

        $this->assertStringContainsString('Milotic (#012/101) — EX Hidden Legends [Holo Rare]', $tester->getDisplay());
    }

    public function testTitleOmitsCollectorNumberAndRarityWhenAbsent(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getMarketplaceListings')->willReturn([
            self::listing([
                'properties_hash' => [
                    'condition' => 'Near Mint',
                    'pokemon_reverse' => false,
                ],
            ]),
        ]);

        $tester = $this->tester($client);
        $tester->execute(['blueprint-id' => '111151']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Milotic — EX Hidden Legends', $display);
        $this->assertStringNotContainsString('#', $display);
        $this->assertStringNotContainsString('[', $display);
    }

    public function testSplitsRegularAndReverseHoloIntoSeparateSections(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getMarketplaceListings')->willReturn([
            self::listing(['price_cents' => 1000]),
            self::listing([
                'price_cents' => 2000,
                'properties_hash' => [
                    'collector_number' => '012/101',
                    'pokemon_rarity' => 'Holo Rare',
                    'pokemon_reverse' => true,
                    'condition' => 'Near Mint',
                ],
            ]),
        ]);

        $tester = $this->tester($client);
        $tester->execute(['blueprint-id' => '111151']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Regular', $display);
        $this->assertStringContainsString('Reverse Holo', $display);
    }

    public function testOrdersConditionsAndAppendsAllRowWithZeroForMissingConditions(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getMarketplaceListings')->willReturn([
            self::listing(['price_cents' => 1000, 'properties_hash' => [
                'collector_number' => '012/101',
                'pokemon_rarity' => 'Holo Rare',
                'pokemon_reverse' => false,
                'condition' => 'Played',
            ]]),
            self::listing(['price_cents' => 2000, 'properties_hash' => [
                'collector_number' => '012/101',
                'pokemon_rarity' => 'Holo Rare',
                'pokemon_reverse' => false,
                'condition' => 'Near Mint',
            ]]),
        ]);

        $tester = $this->tester($client);
        $tester->execute(['blueprint-id' => '111151']);

        $display = $tester->getDisplay();
        $nearMintPos = strpos($display, 'Near Mint');
        $playedPos = strpos($display, 'Played');
        $allPos = strpos($display, 'ALL');

        $this->assertNotFalse($nearMintPos);
        $this->assertNotFalse($playedPos);
        $this->assertNotFalse($allPos);
        $this->assertLessThan($playedPos, $nearMintPos);
        $this->assertLessThan($allPos, $playedPos);

        // A condition with no listings (e.g. Poor) still gets a zero-count row.
        $this->assertMatchesRegularExpression('/Poor\s+0\s/', $display);
        $this->assertMatchesRegularExpression('/ALL\s+2\s/', $display);
    }

    public function testUnknownConditionIsAppendedAfterTheStandardOrder(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getMarketplaceListings')->willReturn([
            self::listing(['properties_hash' => [
                'collector_number' => '012/101',
                'pokemon_rarity' => 'Holo Rare',
                'pokemon_reverse' => false,
                'condition' => 'Damaged',
            ]]),
        ]);

        $tester = $this->tester($client);
        $tester->execute(['blueprint-id' => '111151']);

        $display = $tester->getDisplay();
        $damagedPos = strpos($display, 'Damaged');
        $allPos = strpos($display, 'ALL');

        $this->assertNotFalse($damagedPos);
        $this->assertLessThan($allPos, $damagedPos);
    }

    public function testFormatsPricesWithCurrency(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getMarketplaceListings')->willReturn([
            self::listing(['price_cents' => 8054, 'price_currency' => 'EUR']),
        ]);

        $tester = $this->tester($client);
        $tester->execute(['blueprint-id' => '111151']);

        $this->assertStringContainsString('80.54 EUR', $tester->getDisplay());
    }
}
