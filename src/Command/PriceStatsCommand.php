<?php

declare(strict_types=1);

namespace App\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
use App\CardTrader\Listing;
use App\CardTrader\ListingStats;
use App\CardTrader\PriceStats;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'price-stats', description: 'Price stats (min/max/avg/median) from live marketplace listings for a blueprint, grouped by condition')]
class PriceStatsCommand extends Command
{
    /** @var list<string> */
    private const VALID_LANGUAGES = ['de', 'en', 'es', 'fr', 'it', 'jp', 'pt'];

    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('blueprint-id', InputArgument::REQUIRED, 'CardTrader blueprint ID (a specific card+print)')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Filter listings by language, e.g. en', 'en');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $blueprintId = (int) $input->getArgument('blueprint-id');

        $language = (string) $input->getOption('language');
        if (!in_array($language, self::VALID_LANGUAGES, true)) {
            $io->error("Invalid language \"{$language}\". Valid languages: " . implode(', ', self::VALID_LANGUAGES));

            return Command::FAILURE;
        }

        $filters = ['language' => $language];

        try {
            $listings = $this->client->getMarketplaceListings($blueprintId, $filters);
        } catch (CardTraderException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($listings === []) {
            $io->warning('No listings found for this blueprint (with the given filters).');

            return Command::SUCCESS;
        }

        $io->title($this->cardTitle($listings[0]));

        $io->text("Language: {$language}");

        foreach (ListingStats::groupByVariant($listings) as $variant => $variantListings) {
            $io->section($variant);
            $io->table(['Condition', 'Count', 'Min', 'Max', 'Avg', 'Median'], $this->buildRows($variantListings));
        }

        return Command::SUCCESS;
    }

    private function cardTitle(Listing $listing): string
    {
        $set = $listing->expansionNameEn ?? '?';
        $title = $listing->collectorNumber !== null
            ? "{$listing->nameEn} (#{$listing->collectorNumber}) — {$set}"
            : "{$listing->nameEn} — {$set}";

        return $listing->rarity !== null ? "{$title} [{$listing->rarity}]" : $title;
    }

    /**
     * @param list<Listing> $listings
     * @return list<list<string>>
     */
    private function buildRows(array $listings): array
    {
        $currency = $listings[0]->priceCurrency;

        $rows = [];
        foreach (ListingStats::byCondition($listings) as $label => $stats) {
            $rows[] = $this->statsRow($label, $stats, $currency);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function statsRow(string $label, PriceStats $stats, string $currency): array
    {
        return [
            $label,
            (string) $stats->count,
            $this->format($stats->minCents, $currency),
            $this->format($stats->maxCents, $currency),
            $this->format($stats->avgCents, $currency),
            $this->format($stats->medianCents, $currency),
        ];
    }

    private function format(int|float|null $cents, string $currency): string
    {
        if ($cents === null) {
            return '-';
        }

        return number_format($cents / 100, 2) . ' ' . $currency;
    }
}
