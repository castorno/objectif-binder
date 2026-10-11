<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\OwnedCard;
use App\Entity\PullRate;
use App\Entity\Rarity;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Which booster to open: what a booster of each set can be expected to
 * bring to a user, given what they own. The arithmetic has its own tests
 * (BoosterOddsCalculatorTest); these check what is counted, and for whom.
 */
final class BoosterControllerTest extends AuthWebTestCase
{
    private Game $game;
    private CardSet $main;
    private CardSet $gallery;
    private Rarity $common;
    private Rarity $rare;
    private Rarity $classic;
    /** @var array<string, CardIdentity> */
    private array $identities = [];
    /** @var array<string, Card> */
    private array $cards = [];

    /**
     * A set of four commons and two rares, with a gallery of two classic
     * cards released in its boosters. Six species; the wyrm is printed three
     * times, the charm is no species at all.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $this->game->setIdentityLabel('Bestiary');
        $this->main = new CardSet($this->game, 'Main Set', 'MAIN');
        $this->gallery = new CardSet($this->game, 'Main Set Gallery', 'MAIN-G')->setParent($this->main);
        $this->common = new Rarity($this->game, 'Common', 1);
        $this->rare = new Rarity($this->game, 'Rare', 2);
        $this->classic = new Rarity($this->game, 'Classic', 3);
        foreach ([$this->game, $this->main, $this->gallery, $this->common, $this->rare, $this->classic] as $entity) {
            $this->em->persist($entity);
        }

        $this->card($this->main, '001', 'Ember Wyrm', $this->common, 'Wyrm');
        $this->card($this->main, '002', 'Frost Fox', $this->common, 'Fox');
        $this->card($this->main, '003', 'Mist Owl', $this->common, 'Owl');
        $this->card($this->main, '004', 'Gust Charm', $this->common);
        $this->card($this->main, '005', 'Storm Wyrm', $this->rare, 'Wyrm');
        $this->card($this->main, '006', 'Stone Golem', $this->rare, 'Golem');
        $this->card($this->gallery, '001', 'Old Wyrm', $this->classic, 'Wyrm');
        $this->card($this->gallery, '002', 'Old Stag', $this->classic, 'Stag');

        // Two commons in every booster, a rare every two boosters, a classic card every ten.
        $this->em->persist(new PullRate($this->main, $this->common, 2, 1));
        $this->em->persist(new PullRate($this->main, $this->rare, 1, 2));
        $this->em->persist(new PullRate($this->main, $this->classic, 1, 10));
        $this->em->flush();
    }

    public function testIsForSignedInUsersOnly(): void
    {
        $this->client->request('GET', '/api/collection/boosters');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * Nothing owned: every species is new, and every card is missing.
     */
    public function testSaysWhatABoosterBringsToSomeoneWhoOwnsNothing(): void
    {
        $booster = $this->booster($this->createUser());

        self::assertSame(['id' => (string) $this->main->getId(), 'name' => 'Main Set', 'code' => 'MAIN', 'releaseDate' => null, 'gameSlug' => $this->game->getSlug(), 'identityLabel' => 'Bestiary'], $booster['set']);
        // The cards of the gallery come out of the same boosters.
        self::assertSame(8, $booster['cardCount']);
        self::assertSame(8, $booster['missingCardCount']);
        self::assertSame(5, $booster['identityCount']);
        self::assertSame(5, $booster['missingIdentityCount']);
        // 2 commons + 0.5 rare + 0.1 classic card.
        self::assertEqualsWithDelta(2.6, $booster['cardsPerBooster'], 0.0001);
        self::assertEqualsWithDelta(2.6, $booster['expectedMissingCards'], 0.0001);
        self::assertFalse($booster['incomplete']);

        // Two commons among four, no card twice: each common species comes in half of the boosters.
        // Wyrm: a common (1/2), a rare (1/2 × 1/2) or a classic card (1/10 × 1/2): 1 − 0.5 × 0.75 × 0.95.
        $chances = array_column($booster['likeliest'], 'chance', 'name');
        self::assertEqualsWithDelta(0.64375, $chances['Wyrm'], 0.0001);
        self::assertEqualsWithDelta(0.5, $chances['Fox'], 0.0001);
        self::assertEqualsWithDelta(0.25, $chances['Golem'], 0.0001);
        self::assertEqualsWithDelta(0.05, $chances['Stag'], 0.0001);
        // The most likely first.
        self::assertSame('Wyrm', $booster['likeliest'][0]['name']);
        // The average number of new species is the sum of their chances: 0.64375 + 0.5 + 0.5 + 0.25 + 0.05.
        self::assertEqualsWithDelta(1.94375, $booster['expectedNewIdentities'], 0.0001);
    }

    /**
     * A species is new as long as the user owns no card of it, wherever
     * that card comes from; a card is missing as long as they do not own it.
     */
    public function testCountsOnlyWhatTheUserDoesNotHave(): void
    {
        $user = $this->createUser();
        // One wyrm owned: the species is no longer new, though two other wyrms are still missing.
        $this->em->persist(new OwnedCard($user, $this->cards['Storm Wyrm'], 'fr'));
        // A fox from another set: the species is known, and the card is not one of these boosters.
        $elsewhere = new CardSet($this->game, 'Elsewhere', 'ELSE');
        $this->em->persist($elsewhere);
        $this->card($elsewhere, '001', 'Dusk Fox', $this->common, 'Fox');
        $this->em->persist(new OwnedCard($user, $this->cards['Dusk Fox'], 'fr'));
        // What someone else owns changes nothing.
        $this->em->persist(new OwnedCard($this->createUser(), $this->cards['Mist Owl'], 'fr'));
        $this->em->flush();

        $booster = $this->booster($user);

        self::assertSame(7, $booster['missingCardCount']);
        self::assertSame(3, $booster['missingIdentityCount']);
        self::assertSame(['Owl', 'Golem', 'Stag'], array_column($booster['likeliest'], 'name'));
        // Owl 0.5 + Golem 0.25 + Stag 0.05.
        self::assertEqualsWithDelta(0.8, $booster['expectedNewIdentities'], 0.0001);
        // The owned rare no longer counts: 2 commons + 0.5 × 1/2 + 0.1.
        self::assertEqualsWithDelta(2.35, $booster['expectedMissingCards'], 0.0001);
    }

    /**
     * A rarity without pull rate is a part of the booster nothing is known
     * of: the figures cover the rest, and say so.
     */
    public function testFlagsABoosterWhosePullRatesAreNotAllEntered(): void
    {
        $this->em->remove($this->em->getRepository(PullRate::class)->findOneBy(['cardSet' => $this->main, 'rarity' => $this->classic]));
        $this->em->flush();

        $booster = $this->booster($this->createUser());

        self::assertTrue($booster['incomplete']);
        self::assertEqualsWithDelta(2.5, $booster['cardsPerBooster'], 0.0001);
        // The stag only exists as a classic card: nothing says it can be pulled.
        self::assertEqualsWithDelta(0.0, array_column($booster['likeliest'], 'chance', 'name')['Stag'], 0.0001);
        self::assertSame(5, $booster['missingIdentityCount']);
    }

    /**
     * Only sets with boosters of their own and pull rates are listed; the
     * others are counted, so that the page can say how many are left out.
     */
    public function testListsTheSetsWithPullRatesTheMostRewardingFirst(): void
    {
        $user = $this->createUser();
        // A richer booster: three new species in every booster.
        $rich = new CardSet($this->game, 'Rich Set', 'RICH');
        $this->em->persist($rich);
        foreach (['Lynx', 'Crane', 'Toad'] as $number => $species) {
            $this->card($rich, (string) $number, 'Wild '.$species, $this->common, $species);
        }
        $this->em->persist(new PullRate($rich, $this->common, 3, 1));
        // No pull rate: nothing can be said about it.
        $this->em->persist(new CardSet($this->game, 'Unknown Set', 'UNK'));
        // Rates left on a sub-set are not those of a booster.
        $this->em->persist(new PullRate($this->gallery, $this->classic, 1, 1));
        $this->em->flush();

        $this->client->request('GET', '/api/collection/boosters?game='.$this->game->getSlug(), server: $this->authorization($user));

        self::assertResponseIsSuccessful();
        self::assertSame(['RICH', 'MAIN'], array_column(array_column($this->responseBody()['data'], 'set'), 'code'));
        self::assertEqualsWithDelta(3.0, $this->responseBody()['data'][0]['expectedNewIdentities'], 0.0001);
        self::assertSame(1, $this->responseBody()['setsWithoutRates']);

        // Another game: none of these.
        $this->client->request('GET', '/api/collection/boosters?game=no-such-game', server: $this->authorization($user));
        self::assertSame(['data' => [], 'setsWithoutRates' => 0], $this->responseBody());
    }

    /**
     * @return array<string, mixed> the booster of the main set, as the API gives it to this user
     */
    private function booster(User $user): array
    {
        $this->client->request('GET', '/api/collection/boosters?game='.$this->game->getSlug(), server: $this->authorization($user));
        self::assertResponseIsSuccessful();

        foreach ($this->responseBody()['data'] as $booster) {
            if ('MAIN' === $booster['set']['code']) {
                return $booster;
            }
        }

        self::fail('The main set is not among the boosters.');
    }

    private function card(CardSet $set, string $number, string $name, Rarity $rarity, ?string $species = null): void
    {
        $card = new Card($set, $name, $number)->setRarity($rarity);
        if (null !== $species) {
            if (!isset($this->identities[$species])) {
                $this->identities[$species] = new CardIdentity($this->game, $species, strtolower($species));
                $this->em->persist($this->identities[$species]);
            }
            $card->addIdentity($this->identities[$species]);
        }
        $this->em->persist($card);
        $this->cards[$name] = $card;
    }

    /**
     * @return array<string, string>
     */
    private function authorization(User $user): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.static::getContainer()->get(JWTTokenManagerInterface::class)->create($user)];
    }
}
