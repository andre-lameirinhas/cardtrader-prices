<?php

declare(strict_types=1);

namespace App\Command;

use App\Card\Card;
use App\Card\CardFileReader;
use App\CardTrader\CardTraderException;
use App\CardTrader\PokemonCatalog;
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
    /** Variant types that live in a sibling expansion named "<main name> - <type>". */
    private const SIBLING_TYPES = ['Poké Ball Reverse Holo', 'Master Ball Reverse Holo'];

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
            $expansions = $this->resolveExpansions($mainExpansionId);
            if ($expansions === null) {
                $io->error("Unknown Pokémon expansion ID {$mainExpansionId}.");

                return Command::FAILURE;
            }

            // $blueprints: the one single per collector number; $ambiguous: numbers shared by several singles.
            $blueprints = [];
            $ambiguous = [];
            foreach ($expansions as $key => $expansionId) {
                $blueprints[$key] = [];
                $ambiguous[$key] = [];
                foreach ($expansionId === null ? [] : $this->catalog->singlesByNumber($expansionId) as $number => $group) {
                    if (count($group) === 1) {
                        $blueprints[$key][$number] = $group[0];
                    } else {
                        $ambiguous[$key][$number] = array_map(static fn (array $b) => (int) $b['id'], $group);
                    }
                }
            }
        } catch (CardTraderException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        foreach (self::SIBLING_TYPES as $type) {
            if ($expansions[$type] === null) {
                $io->note("No \"{$type}\" expansion found; those variants will be unmatched.");
            }
        }

        $mapping = [];
        $unmatched = [];
        $matchedCount = 0;

        foreach ($cards as $card) {
            $number = $card->collectorNumber();
            $base = $blueprints['main'][$number] ?? null;

            foreach ($ambiguous as $key => $shared) {
                if (isset($shared[$number])) {
                    $io->warning("#{$number}: " . self::sharedBy($shared[$number], (int) $expansions[$key]) . '; those variants are left unmatched.');
                }
            }

            if ($base !== null && strcasecmp((string) $base['name'], $card->name) !== 0) {
                $io->warning("#{$number}: local name \"{$card->name}\" differs from CardTrader \"{$base['name']}\" (blueprint {$base['id']}).");
            }

            $variants = [];
            foreach ($card->variants as $variant) {
                $result = $this->mapVariant($variant->type, $number, $base, $blueprints, $ambiguous, $expansions);
                $variants[] = ['variant_id' => $variant->id, 'type' => $variant->type, ...$result];

                if ($result['blueprint_id'] === null) {
                    $unmatched[] = [$card->number, $card->name, $variant->id, $variant->type, $result['reason']];
                } else {
                    ++$matchedCount;
                }
            }

            $mapping[] = [
                'card_id' => $card->id,
                'number' => $card->number,
                'name' => $card->name,
                'blueprint_id' => $base['id'] ?? null,
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
     * @param array<string, array<string, array<string, mixed>>> $blueprints
     * @param array<string, array<string, list<int>>> $ambiguous
     * @param array<string, int|null> $expansions
     * @param array<string, mixed>|null $base
     * @return array{blueprint_id: int, expansion_id: int, reverse: bool}|array{blueprint_id: null, reason: string}
     */
    private function mapVariant(string $type, string $number, ?array $base, array $blueprints, array $ambiguous, array $expansions): array
    {
        if (in_array($type, self::SIBLING_TYPES, true)) {
            $blueprint = $blueprints[$type][$number] ?? null;
            if ($blueprint === null) {
                return ['blueprint_id' => null, 'reason' => match (true) {
                    $expansions[$type] === null => "no {$type} expansion",
                    isset($ambiguous[$type][$number]) => self::sharedBy($ambiguous[$type][$number], $expansions[$type]),
                    default => "#{$number} not in expansion {$expansions[$type]}",
                }];
            }

            return ['blueprint_id' => (int) $blueprint['id'], 'expansion_id' => (int) $expansions[$type], 'reverse' => false];
        }

        $reverse = match ($type) {
            'Normal', 'Normal Holo' => false,
            'Reverse Holo' => true,
            default => null,
        };

        if ($reverse === null) {
            return ['blueprint_id' => null, 'reason' => 'no mapping rule for this variant type'];
        }

        if ($base === null) {
            return ['blueprint_id' => null, 'reason' => isset($ambiguous['main'][$number])
                ? self::sharedBy($ambiguous['main'][$number], (int) $expansions['main'])
                : "#{$number} not in expansion {$expansions['main']}"];
        }

        if ($reverse && !self::supportsReverse($base)) {
            return ['blueprint_id' => null, 'reason' => "blueprint {$base['id']} has no reverse option"];
        }

        return ['blueprint_id' => (int) $base['id'], 'expansion_id' => (int) $expansions['main'], 'reverse' => $reverse];
    }

    /**
     * The main expansion plus its Poké Ball / Master Ball siblings (null when CardTrader has none),
     * or null when the main expansion ID is unknown.
     *
     * @return array<string, int|null>|null
     */
    private function resolveExpansions(int $mainId): ?array
    {
        $byName = [];
        $mainName = null;
        foreach ($this->catalog->expansions() as $expansion) {
            $byName[(string) $expansion['name']] = (int) $expansion['id'];
            if ((int) $expansion['id'] === $mainId) {
                $mainName = (string) $expansion['name'];
            }
        }

        if ($mainName === null) {
            return null;
        }

        $resolved = ['main' => $mainId];
        foreach (self::SIBLING_TYPES as $type) {
            $resolved[$type] = $byName["{$mainName} - {$type}"] ?? null;
        }

        return $resolved;
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
     * @param list<int> $blueprintIds
     */
    private static function sharedBy(array $blueprintIds, int $expansionId): string
    {
        return 'collector number shared by blueprints ' . implode(', ', $blueprintIds) . " in expansion {$expansionId}";
    }

    /**
     * "001" → "1", so --number 1 and --number 001 select the same card.
     */
    private static function withoutLeadingZeros(string $number): string
    {
        $trimmed = ltrim(trim($number), '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * @param array<string, mixed> $blueprint
     */
    private static function supportsReverse(array $blueprint): bool
    {
        foreach ($blueprint['editable_properties'] ?? [] as $property) {
            if (($property['name'] ?? null) === 'pokemon_reverse') {
                return true;
            }
        }

        return false;
    }
}
