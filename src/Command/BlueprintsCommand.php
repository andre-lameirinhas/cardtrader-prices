<?php

declare(strict_types=1);

namespace App\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
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
    private const SINGLES_CATEGORY_ID = 73;

    public function __construct(private readonly Client $client)
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
        $rawId = trim((string) $input->getArgument('expansion-id'));
        $includeAll = (bool) $input->getOption('all');

        if (!ctype_digit($rawId)) {
            $io->error("Invalid expansion ID \"{$rawId}\". Look it up with: bin/console expansions <name>");

            return Command::FAILURE;
        }

        $expansionId = (int) $rawId;

        try {
            $blueprints = $this->client->getBlueprints($expansionId);
        } catch (CardTraderException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$includeAll) {
            $blueprints = array_values(array_filter(
                $blueprints,
                static fn (array $b) => ($b['category_id'] ?? null) === self::SINGLES_CATEGORY_ID,
            ));
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
