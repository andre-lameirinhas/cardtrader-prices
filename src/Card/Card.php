<?php

declare(strict_types=1);

namespace App\Card;

/**
 * A card from a local *.card.json file.
 */
final class Card
{
    /**
     * @param list<CardVariant> $variants
     */
    public function __construct(
        public readonly string $id,
        public readonly string $number,
        public readonly string $name,
        public readonly array $variants = [],
    ) {
    }

    /**
     * @param array<mixed> $data
     * @throws InvalidCardFileException
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'number', 'name'] as $key) {
            if (!isset($data[$key]) || !is_scalar($data[$key])) {
                throw new InvalidCardFileException("Card is missing \"{$key}\"");
            }
        }

        $variants = $data['variants'] ?? [];
        if (!is_array($variants)) {
            throw new InvalidCardFileException('Card "variants" is not a list');
        }

        return new self(
            id: (string) $data['id'],
            number: (string) $data['number'],
            name: (string) $data['name'],
            variants: array_values(array_map(CardVariant::fromArray(...), $variants)),
        );
    }

    /**
     * "001/086" → "001".
     */
    public function collectorNumber(): string
    {
        return explode('/', $this->number)[0];
    }
}
