<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
use App\Command\ExpansionsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ExpansionsCommandTest extends TestCase
{
    private const EXPANSIONS = [
        ['id' => 4266, 'game_id' => 5, 'code' => 'p-blk', 'name' => 'Black Bolt - Poké Ball Reverse Holo'],
        ['id' => 4195, 'game_id' => 5, 'code' => 'blk', 'name' => 'Black Bolt'],
        ['id' => 4188, 'game_id' => 5, 'code' => 'sv11b', 'name' => 'Black Bolt | sv11B'],
        ['id' => 2069, 'game_id' => 5, 'code' => 'sm9', 'name' => 'Tag Bolt'],
        ['id' => 2177, 'game_id' => 6, 'code' => 'bol', 'name' => 'Boltyn Blitz Deck'],
    ];

    /**
     * @return array{0: string, 1: int}
     */
    private function execute(string $query, ?Client $client = null): array
    {
        if ($client === null) {
            $client = $this->createMock(Client::class);
            $client->method('getExpansions')->willReturn(self::EXPANSIONS);
        }

        $tester = new CommandTester(new ExpansionsCommand($client));
        $exitCode = $tester->execute(['query' => $query]);

        return [$tester->getDisplay(), $exitCode];
    }

    public function testMatchesNameSubstringIgnoringCaseAndSpacesSortedById(): void
    {
        [$display, $exitCode] = $this->execute('blackbolt');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertMatchesRegularExpression('/4188\s+sv11b.*4195\s+blk.*4266\s+p-blk/s', $display);
        $this->assertStringNotContainsString('Tag Bolt', $display);
    }

    public function testMatchesCode(): void
    {
        [$display] = $this->execute('SM9');

        $this->assertMatchesRegularExpression('/2069\s+sm9\s+Tag Bolt/', $display);
        $this->assertStringNotContainsString('Black Bolt', $display);
    }

    public function testExcludesOtherGames(): void
    {
        [$display] = $this->execute('bolt');

        $this->assertStringContainsString('Tag Bolt', $display);
        $this->assertStringNotContainsString('Boltyn', $display);
    }

    public function testWarnsWhenNothingMatches(): void
    {
        [$display, $exitCode] = $this->execute('nonexistent');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No Pokémon expansion matches "nonexistent"', $display);
    }

    public function testReportsClientErrorsAsFailure(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getExpansions')->willThrowException(new CardTraderException('boom'));

        [$display, $exitCode] = $this->execute('blk', $client);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('boom', $display);
    }
}
