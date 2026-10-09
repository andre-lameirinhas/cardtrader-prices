<?php

declare(strict_types=1);

namespace App\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\PokemonCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'blueprints', description: 'List the blueprints (card+print IDs) of a Pokémon set')]
class BlueprintsCommand extends Command
{
    public function __construct(private readonly PokemonCatalog $catalog)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('expansion-id', InputArgument::REQUIRED, 'CardTrader expansion ID (find it with the expansions command)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Include sealed product and accessories, not just singles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $includeAll = (bool) $input->getOption('all');

        $expansionId = ExpansionIdArgument::parse((string) $input->getArgument('expansion-id'), $io);
        if ($expansionId === null) {
            return Command::FAILURE;
        }

        try {
            $blueprints = $includeAll
                ? $this->catalog->blueprints($expansionId)
                : $this->catalog->singles($expansionId);
        } catch (CardTraderException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        usort($blueprints, static function (array $a, array $b): int {
            // Singles (with a collector number) first, in natural order; everything else by name.
            $numA = $a['fixed_properties']['collector_number'] ?? null;
            $numB = $b['fixed_properties']['collector_number'] ?? null;
            if ($numA === null || $numB === null) {
                return [$numA === null, $a['name'] ?? ''] <=> [$numB === null, $b['name'] ?? ''];
            }

            return strnatcmp((string) $numA, (string) $numB);
        });

        $io->title("Expansion #{$expansionId} — " . count($blueprints) . ' blueprints');

        if ($blueprints === []) {
            $io->warning('No blueprints found for this expansion.');

            return Command::SUCCESS;
        }

        $io->table(['ID', '#', 'Name', 'Rarity'], array_map(static fn (array $b) => [
            (string) $b['id'],
            (string) ($b['fixed_properties']['collector_number'] ?? ''),
            (string) ($b['name'] ?? ''),
            (string) ($b['fixed_properties']['pokemon_rarity'] ?? ''),
        ], $blueprints));

        return Command::SUCCESS;
    }
}
