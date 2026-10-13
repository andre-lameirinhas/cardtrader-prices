<?php

declare(strict_types=1);

namespace App\Command;

use App\Card\Card;
use App\Card\CardFileReader;
use App\CardTrader\CardTraderException;
use App\CardTrader\PokemonCatalog;
use App\Mapping\VariantMatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'map-cards', description: 'Map a folder of *.card.json files (and their variants) to CardTrader blueprint IDs')]
class MapCardsCommand extends Command
{
    public function __construct(
        private readonly PokemonCatalog $catalog,
        private readonly CardFileReader $cardReader = new CardFileReader(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dir', InputArgument::REQUIRED, 'Folder containing *.card.json files')
            ->addArgument('expansion-id', InputArgument::REQUIRED, 'CardTrader main expansion ID (find it with the expansions command)')
            ->addOption('number', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only map these collector numbers, e.g. --number 1 (leading zeros optional)')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write the JSON mapping to this file instead of stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // JSON may go to stdout, so all human-readable output goes to stderr.
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $dir = rtrim((string) $input->getArgument('dir'), '/');
        /** @var list<string> $numbers */
        $numbers = $input->getOption('number');
        $outputPath = $input->getOption('output');

        $mainExpansionId = ExpansionIdArgument::parse((string) $input->getArgument('expansion-id'), $io);
        if ($mainExpansionId === null) {
            return Command::FAILURE;
        }

        if (!is_dir($dir)) {
            $io->error("Directory not found: {$dir}");

            return Command::FAILURE;
        }

        $cards = $this->loadCards($dir, $numbers, $io);
        if ($cards === []) {
            $io->warning('No matching card files found.');

            return Command::SUCCESS;
        }

        try {
            $expansions = $this->catalog->expansionWithSiblings($mainExpansionId);
            if ($expansions === null) {
                $io->error("Unknown Pokémon expansion ID {$mainExpansionId}.");

                return Command::FAILURE;
            }

            $singlesByNumber = [];
            foreach ($expansions as $key => $expansionId) {
                $singlesByNumber[$key] = $expansionId === null ? [] : $this->catalog->singlesByNumber($expansionId);
            }
        } catch (CardTraderException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        foreach (PokemonCatalog::SIBLING_TYPES as $type) {
            if ($expansions[$type] === null) {
                $io->note("No \"{$type}\" expansion found; those variants will be unmatched.");
            }
        }

        $matcher = new VariantMatcher($expansions, $singlesByNumber);

        $mapping = [];
        $unmatched = [];
        $matchedCount = 0;

        foreach ($cards as $card) {
            $match = $matcher->match($card);

            foreach ($match->warnings as $warning) {
                $io->warning($warning);
            }

            $variants = [];
            foreach ($match->variants as $result) {
                $variants[] = $result->blueprintId === null
                    ? ['variant_id' => $result->variant->id, 'type' => $result->variant->type, 'blueprint_id' => null, 'reason' => $result->reason]
                    : ['variant_id' => $result->variant->id, 'type' => $result->variant->type, 'blueprint_id' => $result->blueprintId, 'expansion_id' => $result->expansionId, 'reverse' => $result->reverse];

                if ($result->blueprintId === null) {
                    $unmatched[] = [$card->number, $card->name, $result->variant->id, $result->variant->type, $result->reason];
                } else {
                    ++$matchedCount;
                }
            }

            $mapping[] = [
                'card_id' => $card->id,
                'number' => $card->number,
                'name' => $card->name,
                'blueprint_id' => $match->blueprintId,
                'variants' => $variants,
            ];
        }

        $json = json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        if (is_string($outputPath) && $outputPath !== '') {
            if (file_put_contents($outputPath, $json) === false) {
                $io->error("Could not write {$outputPath}");

                return Command::FAILURE;
            }
            $io->success("Wrote mapping to {$outputPath}");
        } else {
            $output->write($json, false, OutputInterface::OUTPUT_RAW);
        }

        $unmappedCards = count(array_filter($mapping, static fn (array $m) => $m['blueprint_id'] === null));
        $io->text(count($mapping) . " cards ({$unmappedCards} without a base blueprint), "
            . "{$matchedCount} variants matched, " . count($unmatched) . ' unmatched.');

        if ($unmatched !== []) {
            $io->table(['#', 'Name', 'Variant ID', 'Type', 'Reason'], $unmatched);
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $numbers
     * @return list<Card>
     */
    private function loadCards(string $dir, array $numbers, SymfonyStyle $io): array
    {
        $all = $this->cardReader->readDirectory(
            $dir,
            static fn (string $file) => $io->warning('Skipping unreadable card file ' . basename($file)),
        );

        $wanted = array_map(self::withoutLeadingZeros(...), $numbers);
        $cards = [];
        $found = [];
        foreach ($all as $card) {
            $number = self::withoutLeadingZeros($card->collectorNumber());
            if ($wanted === [] || in_array($number, $wanted, true)) {
                $cards[] = $card;
                $found[$number] = true;
            }
        }

        foreach ($numbers as $i => $requested) {
            if (!isset($found[$wanted[$i]])) {
                $io->warning("No card file for --number {$requested}");
            }
        }

        return $cards;
    }

    /**
     * "001" → "1", so --number 1 and --number 001 select the same card.
     */
    private static function withoutLeadingZeros(string $number): string
    {
        $trimmed = ltrim(trim($number), '0');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
