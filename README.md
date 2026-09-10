# cardtrader-prices

CLI tool to fetch card price stats from the [CardTrader](https://www.cardtrader.com/) API.

## Setup

```bash
composer install
cp .env.example .env
# then edit .env and set CARDTRADER_API_TOKEN
```

## Usage

```bash
bin/console games   # smoke test: lists games known to CardTrader
```

## Tests

```bash
vendor/bin/phpunit
```

## Status

Scaffolding only. `src/CardTrader/Client.php` is a thin authenticated Guzzle wrapper
against the CardTrader API v2 base URI. Price-stats endpoints/commands are pending
the actual API docs (marketplace listings schema, rate limits, pagination).
