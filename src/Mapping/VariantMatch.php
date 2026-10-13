<?php

declare(strict_types=1);

namespace App\Mapping;

use App\Card\CardVariant;

/**
 * The result of matching one card variant: either a blueprint (and how it maps), or null with a reason.
 */
final class VariantMatch
{
    private function __construct(
        public readonly CardVariant $variant,
        public readonly ?int $blueprintId,
        public readonly ?int $expansionId,
        public readonly ?bool $reverse,
        public readonly ?string $reason,
    ) {
    }

    public static function matched(CardVariant $variant, int $blueprintId, int $expansionId, bool $reverse): self
    {
        return new self($variant, $blueprintId, $expansionId, $reverse, null);
    }

    public static function unmatched(CardVariant $variant, string $reason): self
    {
        return new self($variant, null, null, null, $reason);
    }
}
