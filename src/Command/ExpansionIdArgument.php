<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

final class ExpansionIdArgument
{
    /**
     * The parsed expansion ID, or null after printing an error when it isn't numeric.
     */
    public static function parse(string $raw, SymfonyStyle $io): ?int
    {
        $raw = trim($raw);
        if (!ctype_digit($raw)) {
            $io->error("Invalid expansion ID \"{$raw}\". Look it up with: bin/console expansions <name>");

            return null;
        }

        return (int) $raw;
    }
}
