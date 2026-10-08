<?php

declare(strict_types=1);

namespace App\Tests\Import\Tcgdex;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Import\Source\Tcgdex\TcgdexClient;
use App\Import\Source\Tcgdex\TcgdexPriceProvider;
use App\Pricing\PriceUnavailableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TcgdexPriceProviderTest extends TestCase
{
    public function testOnlyKnowsTheCardsThatCameFromTcgdex(): void
    {
        $provider = $this->provider(new MockHttpClient([]));

        self::assertTrue($provider->supports($this->card('pokemon', 'swsh3-136')));
        self::assertFalse($provider->supports($this->card('pokemon', null)));
        self::assertFalse($provider->supports($this->card('lumenfall', 'lf1-012')));
    }

    public function testReadsTheCardmarketPricesInCents(): void
    {
        $response = new MockResponse(json_encode(['id' => 'swsh3-136', 'pricing' => [
            'cardmarket' => [
                'updated' => '2026-10-08T09:52:36.773Z', 'unit' => 'EUR',
                'avg' => 0.07, 'low' => 0.02, 'trend' => 0.07, 'avg1' => 0.02, 'avg7' => 0.08, 'avg30' => 0.08,
                'avg-holo' => 0.28, 'low-holo' => 0.04, 'trend-holo' => 0.27, 'avg30-holo' => 0.29,
            ],
            'tcgplayer' => ['unit' => 'USD', 'normal' => ['marketPrice' => 0.27]],
        ]], \JSON_THROW_ON_ERROR));

        $quote = $this->provider(new MockHttpClient([$response], 'https://api.example'))->fetch($this->card('pokemon', 'swsh3-136'));

        self::assertSame('https://api.example/v2/fr/cards/swsh3-136', $response->getRequestUrl());
        self::assertNotNull($quote);
        self::assertSame('Cardmarket', $quote->marketplace);
        self::assertSame('EUR', $quote->currency);
        self::assertSame(7, $quote->trendCents);
        self::assertSame(2, $quote->lowCents);
        self::assertSame(8, $quote->average30DaysCents);
        self::assertSame(27, $quote->holoTrendCents);
        self::assertSame(4, $quote->holoLowCents);
        // 0.29 * 100 is 28.999… in floating point: rounded, not cut.
        self::assertSame(29, $quote->holoAverage30DaysCents);
        self::assertSame('2026-10-08', $quote->sourceUpdatedAt?->format('Y-m-d'));
    }

    public function testAZeroOrMissingAmountIsNoAmount(): void
    {
        $response = new MockResponse('{"pricing": {"cardmarket": {"unit": "EUR", "trend": 0, "low": null, "avg30": 12.5}}}');

        $quote = $this->provider(new MockHttpClient([$response]))->fetch($this->card('pokemon', 'base1-4'));

        self::assertNull($quote?->trendCents);
        self::assertNull($quote?->lowCents);
        self::assertSame(1250, $quote?->average30DaysCents);
        self::assertNull($quote?->holoTrendCents);
    }

    public function testHasNoQuoteForACardWithoutMarketPrices(): void
    {
        $provider = $this->provider(new MockHttpClient([new MockResponse('{"id": "base1-4", "pricing": null}'), new MockResponse('{"id": "base1-4"}')]));

        self::assertNull($provider->fetch($this->card('pokemon', 'base1-4')));
        self::assertNull($provider->fetch($this->card('pokemon', 'base1-4')));
    }

    public function testAFailureOfTcgdexIsAPriceThatCannotBeAskedFor(): void
    {
        $provider = $this->provider(new MockHttpClient([new MockResponse('Server error', ['http_code' => 500])]));

        $this->expectException(PriceUnavailableException::class);

        $provider->fetch($this->card('pokemon', 'base1-4'));
    }

    private function provider(MockHttpClient $http): TcgdexPriceProvider
    {
        return new TcgdexPriceProvider(new TcgdexClient($http, new MockClock(), pauseMilliseconds: 0));
    }

    private function card(string $gameSlug, ?string $externalId): Card
    {
        return new Card(new CardSet(new Game('Game', $gameSlug), 'Set', 'SET'), 'Card', '1')->setExternalId($externalId);
    }
}
