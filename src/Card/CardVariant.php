<?php

declare(strict_types=1);

namespace App\Card;

final class CardVariant
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
    ) {
    }

    /**
     * @throws InvalidCardFileException
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !isset($data['id'], $data['type']) || !is_scalar($data['id']) || !is_scalar($data['type'])) {
            throw new InvalidCardFileException('Variant is missing "id" or "type"');
        }

        return new self((string) $data['id'], (string) $data['type']);
    }
}
