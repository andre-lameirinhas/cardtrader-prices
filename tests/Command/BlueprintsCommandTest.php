<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
use App\Command\BlueprintsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class BlueprintsCommandTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function single(int $id, string $number, string $name, string $rarity = 'Common'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'category_id' => 73,
            'fixed_properties' => ['collector_number' => $number, 'pokemon_rarity' => $rarity],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function sealed(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name, 'category_id' => 68, 'fixed_properties' => []];
    }

    private function clientReturning(int $expectedExpansionId): Client
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('getExpansions');
        $client->expects($this->once())
            ->method('getBlueprints')
            ->with($expectedExpansionId)
            ->willReturn([
                self::sealed(331810, 'Black Bolt Elite Trainer Box'),
                self::single(342230, '100', 'Zekrom', 'Rare'),
                self::single(342228, '001', 'Snivy'),
                self::single(342229, '020', 'Servine', 'Uncommon'),
            ]);

        return $client;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: string, 1: int}
     */
    private function execute(Client $client, array $input): array
    {
        $tester = new CommandTester(new BlueprintsCommand($client));
        $exitCode = $tester->execute($input);

        return [$tester->getDisplay(), $exitCode];
    }

    public function testFetchesBlueprintsForExpansionId(): void
    {
        [$display, $exitCode] = $this->execute($this->clientReturning(4195), ['expansion-id' => '4195']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Expansion #4195 — 3 blueprints', $display);
    }

    public function testRejectsNonNumericExpansionId(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('getBlueprints');

        [$display, $exitCode] = $this->execute($client, ['expansion-id' => 'blk']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid expansion ID "blk"', $display);
        $this->assertStringContainsString('bin/console expansions', $display);
    }

    public function testShowsOnlySinglesSortedByCollectorNumber(): void
    {
        [$display] = $this->execute($this->clientReturning(4195), ['expansion-id' => '4195']);

        $this->assertStringNotContainsString('Elite Trainer Box', $display);
        $snivy = strpos($display, 'Snivy');
        $servine = strpos($display, 'Servine');
        $zekrom = strpos($display, 'Zekrom');
        $this->assertNotFalse($snivy);
        $this->assertLessThan($servine, $snivy);
        $this->assertLessThan($zekrom, $servine);
        $this->assertMatchesRegularExpression('/342228\s+001\s+Snivy\s+Common/', $display);
    }

    public function testAllOptionIncludesSealedProductAfterSingles(): void
    {
        [$display] = $this->execute($this->clientReturning(4195), ['expansion-id' => '4195', '--all' => true]);

        $this->assertStringContainsString('4 blueprints', $display);
        $this->assertLessThan(strpos($display, 'Elite Trainer Box'), strpos($display, 'Zekrom'));
    }

    public function testReportsClientErrorsAsFailure(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getBlueprints')->willThrowException(new CardTraderException('boom'));

        [$display, $exitCode] = $this->execute($client, ['expansion-id' => '4195']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('boom', $display);
    }
}
