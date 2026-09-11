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

A blueprint ID identifies a specific card+print (e.g. Base Set Charizard). You can look one up
via the raw API, e.g. to find cards by name within an expansion:

```bash
TOKEN=$(grep CARDTRADER_API_TOKEN .env | cut -d= -f2)
curl -s -H "Authorization: Bearer $TOKEN" \
  "https://api.cardtrader.com/api/v2/blueprints?expansion_id=1472" \
  | php -r '$d=json_decode(stream_get_contents(STDIN),true); foreach($d as $b) if(stripos($b["name"],"charizard")!==false) echo $b["id"]." ".$b["name"]."\n";'
```

(Expansion IDs come from `GET /expansions` — note the API ignores the `game_id` query filter
server-side, so filter the response client-side, e.g. by `game_id === 5` for Pokémon.)

### Options

```bash
bin/console price-stats 111151                  # Base Set Charizard
bin/console price-stats 111151 --language=en    # only English listings
```

### Output

For each variant present (Regular / Reverse Holo — a single blueprint can list both), a table of
count/min/max/avg/median grouped by condition, always in Mint → Poor order with a zero row for any
missing condition, and an `ALL` row last:

```
Milotic (#012/101) — EX Hidden Legends [Holo Rare]

Regular
-------
 Condition           Count   Min        Max         Avg         Median
 Mint                0       -          -           -           -
 Near Mint           1       80.54 EUR  80.54 EUR   80.54 EUR   80.54 EUR
 Slightly Played     10      10.36 EUR  97.19 EUR   41.80 EUR   24.40 EUR
 ...
 ALL                 44      2.81 EUR   97.19 EUR   19.91 EUR   11.99 EUR
```

## Tests

```bash
vendor/bin/phpunit
```

## Status / limitations

CardTrader has no historical-price endpoint — only live marketplace listings
(`GET /marketplace/products?blueprint_id=<id>`). Everything here is a point-in-time snapshot;
building a price history means polling this periodically and storing your own snapshots
(not implemented yet).
