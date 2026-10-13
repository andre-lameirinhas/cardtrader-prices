<?php

declare(strict_types=1);

namespace App\Tests\CardTrader;

use App\CardTrader\Expansion;
use PHPUnit\Framework\TestCase;

class ExpansionTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $expansion = Expansion::fromArray(['id' => 4195, 'name' => 'Black Bolt', 'code' => 'blk', 'game_id' => 5]);

        $this->assertSame(4195, $expansion->id);
        $this->assertSame('Black Bolt', $expansion->name);
        $this->assertSame('blk', $expansion->code);
        $this->assertSame(5, $expansion->gameId);
    }

    public function testMissingGameIdBecomesNull(): void
    {
        $expansion = Expansion::fromArray(['id' => 2, 'name' => 'No game', 'code' => 'ng']);

        $this->assertNull($expansion->gameId);
    }
}
