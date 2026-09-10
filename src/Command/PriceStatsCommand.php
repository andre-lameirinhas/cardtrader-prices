<?php

declare(strict_types=1);

namespace App\Command;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
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
    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('blueprint-id', InputArgument::REQUIRED, 'CardTrader blueprint ID (a specific card+print)')
            ->addOption('language', null, InputOption::VALUE_REQUIRED, 'Filter listings by language, e.g. en')
            ->addOption('foil', null, InputOption::VALUE_NONE, 'Only foil listings')
            ->addOption('ct-zero', null, InputOption::VALUE_NONE, 'Only CT Zero / hub seller listings');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $blueprintId = (int) $input->getArgument('blueprint-id');

        $filters = [];
        if ($input->getOption('language') !== null) {
            $filters['language'] = $input->getOption('language');
        }
        if ($input->getOption('foil')) {
            $filters['foil'] = true;
        }
        if ($input->getOption('ct-zero')) {
            $filters['ct_zero'] = true;
        }

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

        foreach ($this->groupByCurrency($listings) as $currency => $currencyListings) {
            $io->section(sprintf('%s (%d listings)', $currency, count($currencyListings)));
            $io->table(['Condition', 'Count', 'Min', 'Max', 'Avg', 'Median'], $this->buildRows($currencyListings, $currency));
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $listings
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByCurrency(array $listings): array
    {
        $groups = [];
        foreach ($listings as $listing) {
            $groups[$listing['price_currency'] ?? '?'][] = $listing;
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>> $listings
     * @return list<list<string>>
     */
    private function buildRows(array $listings, string $currency): array
    {
        $byCondition = [];
        foreach ($listings as $listing) {
            $condition = $listing['properties_hash']['condition'] ?? 'Unknown';
            $byCondition[$condition][] = $listing['price_cents'];
        }

        $rows = [];
        foreach ($byCondition as $condition => $cents) {
            $rows[] = $this->statsRow($condition, $cents, $currency);
        }

        $allCents = array_map(static fn (array $l) => $l['price_cents'], $listings);
        $rows[] = $this->statsRow('ALL', $allCents, $currency);

        return $rows;
    }

    /**
     * @param list<int> $cents
     * @return list<string>
     */
    private function statsRow(string $label, array $cents, string $currency): array
    {
        $stats = PriceStats::fromCents($cents);

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
