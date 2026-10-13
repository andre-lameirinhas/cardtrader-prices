<?php

declare(strict_types=1);

namespace App\Mapping;

/**
 * The result of matching one card: its base blueprint (if any), each variant's match, and any
 * warnings worth showing the user (a shared collector number, a local/CardTrader name mismatch).
 */
final class CardMatch
{
    /**
     * @param list<VariantMatch> $variants
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly ?int $blueprintId,
        public readonly array $variants,
        public readonly array $warnings = [],
    ) {
    }
}
