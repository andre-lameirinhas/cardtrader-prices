<?php

declare(strict_types=1);

namespace App\CardTrader;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;

class Client
{
    private HttpClient $http;

    public function __construct(
        string $baseUri,
        private readonly string $apiToken,
        ?HandlerStack $handlerStack = null,
    ) {
        $this->http = new HttpClient([
            'base_uri' => rtrim($baseUri, '/') . '/',
            'timeout' => 15,
            ...($handlerStack !== null ? ['handler' => $handlerStack] : []),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    /**
     * Live marketplace listings for a single blueprint (a specific card/print),
     * sorted ascending by price. There is no historical-price endpoint; build
     * time series by polling this and storing your own snapshots.
     *
     * @param array{language?: string, foil?: bool, ct_zero?: bool} $filters
     * @return list<array<string, mixed>>
     */
    public function getMarketplaceListings(int $blueprintId, array $filters = []): array
    {
        $response = $this->get('marketplace/products', ['blueprint_id' => $blueprintId, ...$filters]);

        return $response[(string) $blueprintId] ?? [];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
        ]);

        try {
            $response = $this->http->request($method, ltrim($path, '/'), $options);
        } catch (GuzzleException $e) {
            throw new CardTraderException(
                sprintf('CardTrader API request failed [%s %s]: %s', $method, $path, $e->getMessage()),
                previous: $e,
            );
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CardTraderException('Failed to decode CardTrader API response: ' . json_last_error_msg());
        }

        return $decoded ?? [];
    }
}
