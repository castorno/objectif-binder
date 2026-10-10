<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardPrice;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\CardFinish;
use App\Pricing\PriceQuote;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The estimated price of a card, as the API gives it. When and how a price
 * is asked from its source is covered by CardPriceServiceTest: here the
 * price is always recent, so nothing is asked from anyone.
 */
final class CardPriceControllerTest extends AuthWebTestCase
{
    /**
     * Looking at a price may send a request to the service providing it:
     * the route is not open to everyone, unlike the rest of the catalog.
     */
    public function testPriceIsForSignedInUsersOnly(): void
    {
        $card = $this->persistCard('Charizard', '004');

        $this->client->request('GET', '/api/cards/'.$card->getId().'/price');

        self::assertResponseStatusCodeSame(401);
        // The card itself stays public.
        $this->client->request('GET', '/api/cards/'.$card->getId());
        self::assertResponseIsSuccessful();
    }

    /**
     * A marketplace reports amounts for a shiny version of nearly every
     * card, including those never printed that way: sellers file listings
     * under the wrong finish. They are only passed on for a card that has a
     * shiny version next to another one.
     *
     * @param list<CardFinish>|null $finishes
     */
    #[DataProvider('finishesAndWhetherTheShinyPriceIsShown')]
    public function testTheShinyPriceIsOnlyGivenForACardThatHasAShinyVersion(?array $finishes, bool $shown): void
    {
        $card = $this->persistCard('Bulbasaur', '044')->setFinishes($finishes);
        $quote = new PriceQuote('Cardmarket', 'EUR', 937, 2, 514, 1537, null, 1291, new \DateTimeImmutable('2026-10-08 09:00:00'), null);
        $this->em->persist(new CardPrice($card, $quote, new \DateTimeImmutable()));
        $this->em->flush();

        $this->get($this->tokenFor($this->createUser()), '/api/cards/'.$card->getId().'/price');

        $price = $this->responseBody()['price'];
        self::assertSame(514, $price['average30DaysCents']);
        self::assertSame($shown ? 1537 : null, $price['holoTrendCents']);
        self::assertSame($shown ? 1291 : null, $price['holoAverage30DaysCents']);
    }


    /**
     * @return iterable<string, array{list<CardFinish>|null, bool}>
     */
    public static function finishesAndWhetherTheShinyPriceIsShown(): iterable
    {
        yield 'only printed plain' => [[CardFinish::Normal], false];
        // The card itself is the shiny one: its price is the main one.
        yield 'only printed holographic' => [[CardFinish::Holo], false];
        yield 'plain and reverse' => [[CardFinish::Normal, CardFinish::Reverse], true];
        yield 'holographic and reverse' => [[CardFinish::Holo, CardFinish::Reverse], true];
        yield 'plain and holographic' => [[CardFinish::Normal, CardFinish::Holo], true];
        yield 'finishes unknown' => [null, true];
    }


    /**
     * Amounts that only exist for a shiny version the card does not have
     * are no price at all.
     */
    public function testACardOnlyPricedForAShinyVersionItDoesNotHaveHasNoPrice(): void
    {
        $card = $this->persistCard('Bulbasaur', '044')->setFinishes([CardFinish::Normal]);
        $quote = new PriceQuote('Cardmarket', 'EUR', null, null, null, 1537, null, 1291, null, null);
        $this->em->persist(new CardPrice($card, $quote, new \DateTimeImmutable()));
        $this->em->flush();

        $this->get($this->tokenFor($this->createUser()), '/api/cards/'.$card->getId().'/price');

        self::assertResponseIsSuccessful();
        self::assertNull($this->responseBody()['price']);
    }


    /**
     * The estimated price of a card, for a signed-in user. Here the price is
     * recent, so nothing is asked from the source it came from.
     */
    public function testASignedInUserGetsTheKnownPriceOfACard(): void
    {
        $user = $this->createUser();
        $priced = $this->persistCard('Charizard', '004');
        $unpriced = $this->persistCard('Potion', '005');
        $quote = new PriceQuote('Cardmarket', 'EUR', 12050, 9000, 11875, null, null, null, new \DateTimeImmutable('2026-10-08 09:00:00'), 'https://market.example.org/products/42');
        $this->em->persist(new CardPrice($priced, $quote, new \DateTimeImmutable()));
        // Another card of the same set under the same name: its price may be
        // the one a source gave to both.
        $twin = $this->persistCard('Charizard', '006', $priced->getCardSet());
        $this->em->persist(new CardPrice($twin, $quote, new \DateTimeImmutable()));
        $alone = $this->persistCard('Blastoise', '007', $priced->getCardSet());
        $this->em->persist(new CardPrice($alone, $quote, new \DateTimeImmutable()));
        $this->em->flush();
        $token = $this->tokenFor($user);

        $this->get($token, '/api/cards/'.$priced->getId().'/price');
        self::assertResponseIsSuccessful();
        $price = $this->responseBody()['price'];
        self::assertSame('Cardmarket', $price['marketplace']);
        self::assertSame('EUR', $price['currency']);
        self::assertSame(12050, $price['trendCents']);
        self::assertSame(9000, $price['lowCents']);
        self::assertSame(11875, $price['average30DaysCents']);
        self::assertNull($price['holoTrendCents']);
        self::assertArrayHasKey('fetchedAt', $price);
        self::assertSame('https://market.example.org/products/42', $price['productUrl']);
        self::assertTrue($price['sharesNameInSet']);

        $this->get($token, '/api/cards/'.$alone->getId().'/price');
        self::assertFalse($this->responseBody()['price']['sharesNameInSet']);

        // A card of a game no price source knows.
        $this->get($token, '/api/cards/'.$unpriced->getId().'/price');
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseBody()['price']);
    }

    private function tokenFor(User $user): string
    {
        // Entities created so far must exist in the database before a request reads them.
        $this->em->flush();
        $this->login($user->getEmail());

        return $this->responseBody()['token'];
    }

    private function get(string $token, string $uri): void
    {
        $this->client->request('GET', $uri, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    private function persistCard(string $name, string $number, ?CardSet $set = null): Card
    {
        if (null === $set) {
            $game = new Game('Game '.uniqid(), 'game-'.uniqid());
            $set = new CardSet($game, 'Set', 'SET-'.uniqid());
            $this->em->persist($game);
            $this->em->persist($set);
        }

        $card = new Card($set, $name, $number);
        $this->em->persist($card);
        $this->em->flush();

        return $card;
    }
}
