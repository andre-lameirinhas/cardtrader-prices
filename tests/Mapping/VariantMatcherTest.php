<?php

declare(strict_types=1);

namespace App\Tests\Mapping;

use App\Card\Card;
use App\Card\CardVariant;
use App\Mapping\VariantMatcher;
use PHPUnit\Framework\TestCase;

class VariantMatcherTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function single(int $id, string $number, string $name = 'Snivy', bool $reversible = false): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'fixed_properties' => ['collector_number' => $number],
            'editable_properties' => $reversible ? [['name' => 'pokemon_reverse']] : [],
        ];
    }

    private static function card(string $number, string ...$variantTypes): Card
    {
        $variants = [];
        foreach ($variantTypes as $i => $type) {
            $variants[] = new CardVariant((string) $i, $type);
        }

        return new Card('card-1', $number, 'Snivy', $variants);
    }

    /**
     * @param array<string, int|null> $expansions
     * @param array<string, array<string, non-empty-list<array<string, mixed>>>> $singlesByNumber
     */
    private static function matcher(array $expansions, array $singlesByNumber): VariantMatcher
    {
        return new VariantMatcher($expansions, $singlesByNumber);
    }

    public function testMatchesNormalAndNormalHoloToTheMainBlueprintWithoutReverse(): void
    {
        $matcher = self::matcher(['main' => 4195], ['main' => ['001' => [self::single(342228, '001')]]]);

        $match = $matcher->match(self::card('001/086', 'Normal', 'Normal Holo'));

        $this->assertSame(342228, $match->blueprintId);
        foreach ($match->variants as $variant) {
            $this->assertSame(342228, $variant->blueprintId);
            $this->assertSame(4195, $variant->expansionId);
            $this->assertFalse($variant->reverse);
        }
    }

    public function testReverseHoloMapsToTheSameBlueprintWithReverseTrueWhenSupported(): void
    {
        $matcher = self::matcher(['main' => 4195], ['main' => ['001' => [self::single(342228, '001', reversible: true)]]]);

        $variant = $matcher->match(self::card('001/086', 'Reverse Holo'))->variants[0];

        $this->assertSame(342228, $variant->blueprintId);
        $this->assertTrue($variant->reverse);
    }

    public function testReverseHoloIsUnmatchedWhenBlueprintHasNoReverseOption(): void
    {
        $matcher = self::matcher(['main' => 4195], ['main' => ['001' => [self::single(342228, '001', reversible: false)]]]);

        $variant = $matcher->match(self::card('001/086', 'Reverse Holo'))->variants[0];

        $this->assertNull($variant->blueprintId);
        $this->assertStringContainsString('no reverse option', (string) $variant->reason);
    }

    public function testUnknownVariantTypeIsUnmatched(): void
    {
        $matcher = self::matcher(['main' => 4195], ['main' => ['001' => [self::single(342228, '001')]]]);

        $variant = $matcher->match(self::card('001/086', 'Tinsel Holo'))->variants[0];

        $this->assertNull($variant->blueprintId);
        $this->assertSame('no mapping rule for this variant type', $variant->reason);
    }

    public function testCardMissingFromExpansionIsUnmatched(): void
    {
        $matcher = self::matcher(['main' => 4195], []);

        $match = $matcher->match(self::card('050/086', 'Normal'));

        $this->assertNull($match->blueprintId);
        $this->assertStringContainsString('#050 not in expansion 4195', (string) $match->variants[0]->reason);
    }

    public function testSiblingVariantMapsToTheSiblingExpansion(): void
    {
        $matcher = self::matcher(
            ['main' => 4195, 'Poké Ball Reverse Holo' => 4266],
            ['main' => ['001' => [self::single(342228, '001')]], 'Poké Ball Reverse Holo' => ['001' => [self::single(343347, '001')]]],
        );

        $variant = $matcher->match(self::card('001/086', 'Poké Ball Reverse Holo'))->variants[0];

        $this->assertSame(343347, $variant->blueprintId);
        $this->assertSame(4266, $variant->expansionId);
        $this->assertFalse($variant->reverse);
    }

    public function testSiblingVariantIsUnmatchedWhenNoSiblingExpansionExists(): void
    {
        $matcher = self::matcher(['main' => 4195, 'Poké Ball Reverse Holo' => null], ['main' => ['001' => [self::single(342228, '001')]]]);

        $variant = $matcher->match(self::card('001/086', 'Poké Ball Reverse Holo'))->variants[0];

        $this->assertNull($variant->blueprintId);
        $this->assertSame('no Poké Ball Reverse Holo expansion', $variant->reason);
    }

    public function testSiblingVariantIsUnmatchedWhenCardMissingFromSiblingExpansion(): void
    {
        $matcher = self::matcher(['main' => 4195, 'Poké Ball Reverse Holo' => 4266], ['main' => ['001' => [self::single(342228, '001')]]]);

        $variant = $matcher->match(self::card('001/086', 'Poké Ball Reverse Holo'))->variants[0];

        $this->assertNull($variant->blueprintId);
        $this->assertSame('#001 not in expansion 4266', $variant->reason);
    }

    public function testSharedCollectorNumberLeavesCardUnmatchedWithAWarning(): void
    {
        $matcher = self::matcher(['main' => 4195], ['main' => ['001' => [self::single(342228, '001'), self::single(365943, '001')]]]);

        $match = $matcher->match(self::card('001/086', 'Normal'));

        $this->assertNull($match->blueprintId);
        $this->assertSame('collector number shared by blueprints 342228, 365943 in expansion 4195', $match->variants[0]->reason);
        $this->assertSame(
            ['#001: collector number shared by blueprints 342228, 365943 in expansion 4195; those variants are left unmatched.'],
            $match->warnings,
        );
    }

    public function testSharedCollectorNumberInSiblingExpansionIsReported(): void
    {
        $matcher = self::matcher(
            ['main' => 4195, 'Poké Ball Reverse Holo' => 4266],
            ['main' => ['001' => [self::single(342228, '001')]], 'Poké Ball Reverse Holo' => ['001' => [self::single(1, '001'), self::single(2, '001')]]],
        );

        $variant = $matcher->match(self::card('001/086', 'Poké Ball Reverse Holo'))->variants[0];

        $this->assertNull($variant->blueprintId);
        $this->assertSame('collector number shared by blueprints 1, 2 in expansion 4266', $variant->reason);
    }

    public function testWarnsWhenLocalNameDiffersFromCardTraderName(): void
    {
        $matcher = self::matcher(['main' => 4195], ['main' => ['001' => [self::single(342228, '001', name: 'Snivy')]]]);

        $match = $matcher->match(new Card('card-1', '001/086', 'Snivyy', [new CardVariant('0', 'Normal')]));

        $this->assertSame(
            ['#001: local name "Snivyy" differs from CardTrader "Snivy" (blueprint 342228).'],
            $match->warnings,
        );
    }

    public function testNoWarningsWhenNameMatchesAndNumberIsUnique(): void
    {
        $matcher = self::matcher(['main' => 4195], ['main' => ['001' => [self::single(342228, '001')]]]);

        $match = $matcher->match(self::card('001/086', 'Normal'));

        $this->assertSame([], $match->warnings);
    }
}
