# cardtrader-prices

CLI tool to fetch Pokémon card price stats from the [CardTrader](https://www.cardtrader.com/) API,
based on live marketplace listings.

## Setup

```bash
composer install
cp .env.example .env
# then edit .env and set CARDTRADER_API_TOKEN
```

## Usage

```bash
bin/console price-stats <blueprint-id>
```

A blueprint ID identifies a specific card+print (e.g. Base Set Charizard). Finding one takes two steps.

1. Find the expansion (set) ID by searching on part of its name or code (case and spaces ignored):

   ```bash
   bin/console expansions "black bolt"
   ```

   ```
    ID     Code      Name
    4188   sv11b     Black Bolt | sv11B
    4195   blk       Black Bolt
    4223   m-sv11b   Black Bolt | sv11B - Master Ball Reverse Holo
    4263   m-blk     Black Bolt - Master Ball Reverse Holo
    4266   p-blk     Black Bolt - Poké Ball Reverse Holo
    4406   p-sv11b   Black Bolt | sv11B - Poké Ball Reverse Holo
   ```

   A single set can have several expansions (e.g. Japanese release, reverse-holo variants), each
   with its own blueprints.

2. List that expansion's blueprints, sorted by collector number:

   ```bash
   bin/console blueprints 4195          # singles only
   bin/console blueprints 4195 --all    # also sealed product / accessories
   ```

Under the hood: `GET /expansions` (Pokémon is `game_id` 5; the API ignores the `game_id` query
filter, so it's filtered client-side) and `GET /blueprints/export?expansion_id=<id>`. Plain
`GET /blueprints` is paginated (50 per page) and silently truncates larger sets.

### Options

```bash
bin/console price-stats 111151                  # Base Set Charizard (defaults to English listings)
bin/console price-stats 111151 -l fr            # only French listings (-l is short for --language)
```

### Output

For each variant present (Regular / Reverse Holo — a single blueprint can list both), a table of
count/min/max/avg/median grouped by condition, always in Near Mint → Poor order with a zero row for
any missing condition, and an `ALL` row last:

```
Milotic (#012/101) — EX Hidden Legends [Holo Rare]

Regular
-------
 Condition           Count   Min        Max         Avg         Median
 Near Mint           1       80.54 EUR  80.54 EUR   80.54 EUR   80.54 EUR
 Slightly Played     10      10.36 EUR  97.19 EUR   41.80 EUR   24.40 EUR
 ...
 ALL                 44      2.81 EUR   97.19 EUR   19.91 EUR   11.99 EUR
```

## Tests

```bash
composer test
```

## Linting & static analysis

```bash
composer lint      # PHP-CS-Fixer, dry-run with diff
composer lint:fix  # PHP-CS-Fixer, applies fixes
composer stan       # PHPStan (level 8)
composer check      # lint + stan + test
```

## Status / limitations

CardTrader has no historical-price endpoint — only live marketplace listings
(`GET /marketplace/products?blueprint_id=<id>`). Everything here is a point-in-time snapshot;
building a price history means polling this periodically and storing your own snapshots
(not implemented yet).
