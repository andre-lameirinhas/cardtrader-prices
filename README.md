# cardtrader-prices

CLI tool to fetch Pokémon card price stats from the [CardTrader](https://www.cardtrader.com/) API,
based on live marketplace listings.

## Setup

Requires PHP 8.2+ and Composer.

```bash
composer install
cp .env.example .env
# then edit .env and set CARDTRADER_API_TOKEN
```

| Variable | Default | |
|---|---|---|
| `CARDTRADER_API_TOKEN` | — | Required. Your CardTrader API token. |
| `CARDTRADER_API_BASE_URI` | `https://api.cardtrader.com/api/v2` | API base URL. |

## Commands

| Command | What it does |
|---|---|
| [`expansions <query>`](#expansions) | Search Pokémon expansions (sets) by name or code to find their IDs |
| [`blueprints <expansion-id>`](#blueprints) | List an expansion's blueprints (card+print IDs) |
| [`price-stats <blueprint-id>`](#price-stats) | Price stats from live listings for one blueprint, grouped by condition |
| [`map-cards <dir> <expansion-id>`](#map-cards) | Match a folder of `*.card.json` files (and their variants) to blueprint IDs |

Run `bin/console` (or `bin/console list`) for the list, and `bin/console help <command>` for a
command's arguments and options.

A **blueprint** identifies a specific card+print (e.g. Base Set Charizard). The usual flow is
`expansions` → `blueprints` → `price-stats`.

### expansions

Search on part of the expansion name or code (case and spaces ignored):

```bash
bin/console expansions "black bolt"
bin/console expansions blk
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

### blueprints

List an expansion's blueprints as a table of ID, collector number, name and rarity, sorted by
collector number:

```bash
bin/console blueprints 4195          # singles only
bin/console blueprints 4195 --all    # also sealed product / accessories (-a)
```

### price-stats

```bash
bin/console price-stats 111151          # Base Set Charizard (defaults to English listings)
bin/console price-stats 111151 -l fr    # only French listings (-l is short for --language)
```

Valid languages: `de`, `en`, `es`, `fr`, `it`, `jp`, `pt`.

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

### map-cards

Given a folder of `*.card.json` files (each with `id`, `number` like `"001/086"`, `name` and a
`variants[]` list of `{id, type}`) and the set's main expansion ID, `map-cards` matches every card
and variant to a CardTrader blueprint by collector number:

```bash
bin/console map-cards ~/Downloads/black-bolt 4195                       # all cards, JSON to stdout
bin/console map-cards ~/Downloads/black-bolt 4195 --number 1            # one card (leading zeros optional)
bin/console map-cards ~/Downloads/black-bolt 4195 --number 1 --number 2 # --number can be repeated
bin/console map-cards ~/Downloads/black-bolt 4195 -o black-bolt-map.json
```

| Variant type | Blueprint |
|---|---|
| Normal, Normal Holo | main expansion, `reverse: false` |
| Reverse Holo | same blueprint, `reverse: true` (the `pokemon_reverse` listing property); unmatched if the blueprint has no reverse option |
| Poké Ball / Master Ball Reverse Holo | the `"<set> - Poké Ball Reverse Holo"` / `"<set> - Master Ball Reverse Holo"` expansion |
| anything else (promos, stamps, Prize Pack…) | unmatched, with a `reason` |

Output is a JSON array with one entry per card:

```json
[
    {
        "card_id": "…",
        "number": "001/086",
        "name": "…",
        "blueprint_id": 123456,
        "variants": [
            { "variant_id": "…", "type": "Normal", "blueprint_id": 123456, "expansion_id": 4195, "reverse": false },
            { "variant_id": "…", "type": "Reverse Holo", "blueprint_id": 123456, "expansion_id": 4195, "reverse": true },
            { "variant_id": "…", "type": "Prize Pack", "blueprint_id": null, "reason": "no mapping rule for this variant type" }
        ]
    }
]
```

The JSON goes to stdout (or the `-o`/`--output` file). The summary, any name-mismatch warnings, and
the table of unmatched variants go to stderr, so piping stdout stays clean.

## Under the hood

- `expansions` / `map-cards`: `GET /expansions`. Pokémon is `game_id` 5; the API ignores the
  `game_id` query filter, so it's filtered client-side.
- `blueprints` / `map-cards`: `GET /blueprints/export?expansion_id=<id>`. Plain `GET /blueprints`
  is paginated (50 per page) and silently truncates larger sets. Singles are `category_id` 73.
- `price-stats`: `GET /marketplace/products?blueprint_id=<id>`.

## Tests

```bash
composer test
```

## Linting & static analysis

```bash
composer lint      # PHP-CS-Fixer, dry-run with diff
composer lint:fix  # PHP-CS-Fixer, applies fixes
composer stan      # PHPStan (level 8)
composer check     # lint + stan + test
```

## Status / limitations

CardTrader has no historical-price endpoint — only live marketplace listings. Everything here is a
point-in-time snapshot; building a price history means polling this periodically and storing your
own snapshots (not implemented yet).
