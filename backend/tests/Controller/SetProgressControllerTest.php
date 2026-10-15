<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\PullRate;
use App\Entity\Rarity;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * What is already entered for each set, shown to an administrator in the
 * list of sets of the pull rates page.
 */
final class SetProgressControllerTest extends AuthWebTestCase
{
    private Game $game;
    private Rarity $common;
    private Rarity $rare;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $this->common = new Rarity($this->game, 'Common', 1);
        $this->rare = new Rarity($this->game, 'Rare', 2);
        foreach ([$this->game, $this->common, $this->rare] as $entity) {
            $this->em->persist($entity);
        }
    }

    public function testOnlyAnAdministratorReadsIt(): void
    {
        $this->em->flush();
        $uri = '/api/admin/games/'.$this->game->getSlug().'/set-progress';

        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', $uri, server: $this->authorization($this->createUser()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testSaysWhetherThePriceAndEveryRateAreEntered(): void
    {
        $empty = $this->setWithCards('EMP');
        $priced = $this->setWithCards('PRI')->setBoosterPriceCents(599);
        $partial = $this->setWithCards('PAR');
        $this->em->persist(new PullRate($partial, $this->common, 4, 1));
        $done = $this->setWithCards('DON')->setBoosterPriceCents(450);
        $this->em->persist(new PullRate($done, $this->common, 4, 1));
        $this->em->persist(new PullRate($done, $this->rare, 1, 6));
        $this->em->flush();

        self::assertSame([
            'DON' => [true, true],
            'EMP' => [false, false],
            'PAR' => [false, false],
            'PRI' => [true, false],
        ], $this->progress());
    }

    /**
     * The cards of a sub-set come in the boosters of the main set: a rarity
     * of theirs left without a rate leaves the main set to complete. The
     * sub-set itself is not listed.
     */
    public function testCountsTheRaritiesOfTheSubSetsInTheMainSet(): void
    {
        $main = $this->setWithCards('MAI');
        $this->em->persist(new PullRate($main, $this->common, 4, 1));
        $this->em->persist(new PullRate($main, $this->rare, 1, 6));
        $gallery = new Rarity($this->game, 'Gallery', 3);
        $this->em->persist($gallery);
        $sub = new CardSet($this->game, 'Sub Set', 'SUB')->setParent($main);
        $this->em->persist($sub);
        $this->em->persist(new Card($sub, 'Gallery Fox', 'G01')->setRarity($gallery));
        $this->em->flush();

        self::assertSame(['MAI' => [false, false]], $this->progress());

        $this->em->persist(new PullRate($main, $gallery, 1, 10));
        $this->em->flush();

        self::assertSame(['MAI' => [false, true]], $this->progress());
    }

    /** A set with a common and a rare card, and nothing entered. */
    private function setWithCards(string $code): CardSet
    {
        $set = new CardSet($this->game, 'Set '.$code, $code);
        $this->em->persist($set);
        $this->em->persist(new Card($set, 'Common '.$code, '001')->setRarity($this->common));
        $this->em->persist(new Card($set, 'Rare '.$code, '002')->setRarity($this->rare));

        return $set;
    }

    private function createAdmin(): User
    {
        $admin = $this->createUser()->setRoles([User::ROLE_ADMIN]);
        $this->em->flush();

        return $admin;
    }

    /**
     * @return array<string, string>
     */
    private function authorization(User $user): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.static::getContainer()->get(JWTTokenManagerInterface::class)->create($user)];
    }

    /**
     * @return array<string, array{bool, bool}> [has a price, has every rate] by set code
     */
    private function progress(): array
    {
        $this->client->request('GET', '/api/admin/games/'.$this->game->getSlug().'/set-progress', server: $this->authorization($this->createAdmin()));
        self::assertResponseIsSuccessful();

        $codes = [];
        foreach ($this->em->getRepository(CardSet::class)->findBy(['game' => $this->game]) as $set) {
            $codes[(string) $set->getId()] = $set->getCode();
        }

        /** @var list<array{setId: string, hasBoosterPrice: bool, hasCompletePullRates: bool}> $rows */
        $rows = json_decode((string) $this->client->getResponse()->getContent(), true);
        $progress = [];
        foreach ($rows as $row) {
            $progress[$codes[$row['setId']]] = [$row['hasBoosterPrice'], $row['hasCompletePullRates']];
        }
        ksort($progress);

        return $progress;
    }
}
