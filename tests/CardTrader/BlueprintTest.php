<?php

declare(strict_types=1);

namespace App\Tests\CardTrader;

use App\CardTrader\Blueprint;
use PHPUnit\Framework\TestCase;

class BlueprintTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $blueprint = Blueprint::fromArray([
            'id' => 342228,
            'name' => 'Snivy',
            'category_id' => 73,
            'fixed_properties' => ['collector_number' => '001', 'pokemon_rarity' => 'Common'],
            'editable_properties' => [['name' => 'pokemon_reverse']],
        ]);

        $this->assertSame(342228, $blueprint->id);
        $this->assertSame('Snivy', $blueprint->name);
        $this->assertSame(73, $blueprint->categoryId);
        $this->assertSame('001', $blueprint->collectorNumber);
        $this->assertSame('Common', $blueprint->rarity);
        $this->assertTrue($blueprint->supportsReverse());
    }

    public function testMissingOptionalFieldsBecomeNull(): void
    {
        $blueprint = Blueprint::fromArray(['id' => 331810, 'name' => 'Elite Trainer Box']);

        $this->assertNull($blueprint->categoryId);
        $this->assertNull($blueprint->collectorNumber);
        $this->assertNull($blueprint->rarity);
        $this->assertFalse($blueprint->supportsReverse());
    }

    public function testEmptyCollectorNumberBecomesNull(): void
    {
        $blueprint = Blueprint::fromArray(['id' => 1, 'name' => 'Fire Energy', 'fixed_properties' => ['collector_number' => '']]);

        $this->assertNull($blueprint->collectorNumber);
    }

    public function testSupportsReverseIsFalseWhenEditablePropertiesLackIt(): void
    {
        $blueprint = Blueprint::fromArray([
            'id' => 1,
            'name' => 'Snivy',
            'editable_properties' => [['name' => 'pokemon_language'], ['name' => 'condition']],
        ]);

        $this->assertFalse($blueprint->supportsReverse());
    }
}
