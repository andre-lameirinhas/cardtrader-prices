<?php

declare(strict_types=1);

namespace App\Tests\CardTrader;

use App\CardTrader\CardTraderException;
use App\CardTrader\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /**
     * @param list<\GuzzleHttp\Psr7\Response|\Throwable> $responses
     * @param list<array{request: \Psr\Http\Message\RequestInterface}>|null $history
     */
    private function clientWith(array $responses, ?array &$history = null): Client
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);

        $history = [];
        $stack->push(Middleware::history($history));

        return new Client('https://api.example.com/v2', 'secret-token', $stack);
    }

    public function testGetMarketplaceListingsReturnsListingsForBlueprint(): void
    {
        $client = $this->clientWith([
            new Response(200, [], json_encode(['111151' => [['id' => 1], ['id' => 2]]])),
        ]);

        $listings = $client->getMarketplaceListings(111151);

        $this->assertSame([['id' => 1], ['id' => 2]], $listings);
    }

    public function testGetMarketplaceListingsReturnsEmptyArrayWhenBlueprintKeyMissing(): void
    {
        $client = $this->clientWith([
            new Response(200, [], json_encode(['999' => [['id' => 1]]])),
        ]);

        $listings = $client->getMarketplaceListings(111151);

        $this->assertSame([], $listings);
    }

    public function testGetMarketplaceListingsSendsBlueprintIdAndFiltersAsQuery(): void
    {
        $client = $this->clientWith([
            new Response(200, [], json_encode(['111151' => []])),
        ], $history);

        $client->getMarketplaceListings(111151, ['language' => 'fr']);

        $query = $history[0]['request']->getUri()->getQuery();
        parse_str($query, $params);

        $this->assertSame([
            'blueprint_id' => '111151',
            'language' => 'fr',
        ], $params);
    }

    public function testRequestSendsAuthorizationAndAcceptHeaders(): void
    {
        $client = $this->clientWith([
            new Response(200, [], json_encode(['111151' => []])),
        ], $history);

        $client->getMarketplaceListings(111151);

        $request = $history[0]['request'];
        $this->assertSame('Bearer secret-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testRequestWrapsTransportFailuresInCardTraderException(): void
    {
        $client = $this->clientWith([
            new ConnectException('Connection refused', new Request('GET', 'marketplace/products')),
        ]);

        $this->expectException(CardTraderException::class);
        $this->expectExceptionMessageMatches('/CardTrader API request failed/');

        $client->getMarketplaceListings(111151);
    }

    public function testRequestThrowsOnInvalidJsonResponse(): void
    {
        $client = $this->clientWith([
            new Response(200, [], 'not json'),
        ]);

        $this->expectException(CardTraderException::class);
        $this->expectExceptionMessageMatches('/Failed to decode CardTrader API response/');

        $client->getMarketplaceListings(111151);
    }

    public function testRequestReturnsEmptyArrayForEmptyBody(): void
    {
        $client = $this->clientWith([
            new Response(200, [], 'null'),
        ]);

        $this->assertSame([], $client->get('marketplace/products'));
    }
}
