<?php

declare(strict_types=1);

namespace App\CardTrader;

final class Expansion
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly ?int $gameId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            gameId: isset($data['game_id']) ? (int) $data['game_id'] : null,
        );
    }
}
