<?php

declare(strict_types=1);

namespace App\Card;

class CardFileReader
{
    /**
     * @throws InvalidCardFileException
     */
    public function readFile(string $path): Card
    {
        $name = basename($path);
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new InvalidCardFileException("Could not read card file {$name}");
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidCardFileException("Invalid JSON in card file {$name}: {$e->getMessage()}", previous: $e);
        }

        if (!is_array($data)) {
            throw new InvalidCardFileException("Card file {$name} does not contain a JSON object");
        }

        try {
            return Card::fromArray($data);
        } catch (InvalidCardFileException $e) {
            throw new InvalidCardFileException("{$e->getMessage()} in card file {$name}", previous: $e);
        }
    }

    /**
     * Every *.card.json file in $dir, sorted by filename. Invalid files are skipped and passed to $onInvalid.
     *
     * @param (\Closure(string, InvalidCardFileException): void)|null $onInvalid
     * @return list<Card>
     */
    public function readDirectory(string $dir, ?\Closure $onInvalid = null): array
    {
        $files = glob(rtrim($dir, '/') . '/*.card.json') ?: [];
        sort($files);

        $cards = [];
        foreach ($files as $file) {
            try {
                $cards[] = $this->readFile($file);
            } catch (InvalidCardFileException $e) {
                if ($onInvalid !== null) {
                    $onInvalid($file, $e);
                }
            }
        }

        return $cards;
    }
}
