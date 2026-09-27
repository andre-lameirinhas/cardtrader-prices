<?php

declare(strict_types=1);

namespace App\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'expansions', description: 'Search Pokémon expansions (sets) by name or code to find their IDs')]
class ExpansionsCommand extends Command
{
    private const POKEMON_GAME_ID = 5;

    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('query', InputArgument::REQUIRED, 'Part of the expansion name or code, e.g. "black bolt" or blk (case and spaces ignored)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $query = trim((string) $input->getArgument('query'));
        $needle = self::normalize($query);

        try {
            $expansions = $this->client->getExpansions();
        } catch (CardTraderException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $matches = array_values(array_filter(
            $expansions,
            static fn (array $e) => ($e['game_id'] ?? null) === self::POKEMON_GAME_ID
                && $needle !== ''
                && (str_contains(self::normalize((string) $e['name']), $needle)
                    || str_contains(self::normalize((string) $e['code']), $needle)),
        ));

        if ($matches === []) {
            $io->warning("No Pokémon expansion matches \"{$query}\".");

            return Command::SUCCESS;
        }

        usort($matches, static fn (array $a, array $b) => $a['id'] <=> $b['id']);

        $io->table(['ID', 'Code', 'Name'], array_map(
            static fn (array $e) => [(string) $e['id'], (string) $e['code'], (string) $e['name']],
            $matches,
        ));

        return Command::SUCCESS;
    }

    private static function normalize(string $value): string
    {
        return strtolower(str_replace(' ', '', $value));
    }
}
