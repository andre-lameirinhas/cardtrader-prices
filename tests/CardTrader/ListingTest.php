<?php

declare(strict_types=1);

namespace App\Tests\CardTrader;

use App\CardTrader\Listing;
use PHPUnit\Framework\TestCase;

class ListingTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $listing = Listing::fromArray([
            'name_en' => 'Milotic',
            'expansion' => ['name_en' => 'EX Hidden Legends'],
            'properties_hash' => [
                'collector_number' => '012/101',
                'pokemon_rarity' => 'Holo Rare',
                'pokemon_reverse' => true,
                'condition' => 'Near Mint',
            ],
            'price_cents' => 1000,
            'price_currency' => 'EUR',
        ]);

        $this->assertSame(1000, $listing->priceCents);
        $this->assertSame('EUR', $listing->priceCurrency);
        $this->assertSame('Milotic', $listing->nameEn);
        $this->assertSame('012/101', $listing->collectorNumber);
        $this->assertSame('EX Hidden Legends', $listing->expansionNameEn);
        $this->assertSame('Holo Rare', $listing->rarity);
        $this->assertTrue($listing->reverseHolo);
        $this->assertSame('Near Mint', $listing->condition);
    }

    public function testMissingOptionalFieldsBecomeNullOrDefault(): void
    {
        $listing = Listing::fromArray([]);

        $this->assertSame(0, $listing->priceCents);
        $this->assertSame('?', $listing->priceCurrency);
        $this->assertSame('?', $listing->nameEn);
        $this->assertNull($listing->collectorNumber);
        $this->assertNull($listing->expansionNameEn);
        $this->assertNull($listing->rarity);
        $this->assertFalse($listing->reverseHolo);
        $this->assertSame('Unknown', $listing->condition);
    }
}
