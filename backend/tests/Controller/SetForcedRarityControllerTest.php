<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\Rarity;
use App\Entity\User;
use App\Import\CardImporter;
use App\Import\ImportedCardFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * The cards of a sub-set come out of the boosters of its main set at a rate
 * of their own. An administrator names the rarity they all get, so that
 * they have a line of their own among the pull rates.
 */
final class SetForcedRarityControllerTest extends AuthWebTestCase
{
    private Game $game;
    private CardSet $main;
    private CardSet $gallery;
    private Rarity $rare;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $this->main = new CardSet($this->game, 'Main Set', 'MAIN');
        $this->gallery = new CardSet($this->game, 'Main Set Gallery', 'MAIN-G')->setParent($this->main);
        $this->rare = new Rarity($this->game, 'Rare', 4);
        foreach ([$this->game, $this->main, $this->gallery, $this->rare] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->persist(new Card($this->main, 'Ember Wyrm', '001')->setRarity($this->rare));
        // The gallery shares a rarity with its main set, and has a card without any.
        $this->em->persist(new Card($this->gallery, 'Old Wyrm', '001')->setRarity($this->rare));
        $this->em->persist(new Card($this->gallery, 'Old Charm', '002'));
        $this->em->flush();
    }

    public function testOnlyAnAdministratorForcesARarity(): void
    {
        $uri = '/api/admin/sets/'.$this->gallery->getId().'/forced-rarity';

        $this->client->jsonRequest('PUT', $uri, ['name' => 'Reprint']);
        self::assertResponseStatusCodeSame(401);

        $this->client->jsonRequest('PUT', $uri, ['name' => 'Reprint'], $this->authorization($this->createUser()));
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['Old Charm' => null, 'Old Wyrm' => 'Rare'], $this->raritiesOf($this->gallery));
    }

    public function testGivesEveryCardOfTheSubSetARarityOfItsOwn(): void
    {
        $this->force($this->gallery, '  Reprint ');

        self::assertResponseIsSuccessful();
        self::assertSame(['forcedRarity' => 'Reprint'], $this->responseBody());
        self::assertSame(['Old Charm' => 'Reprint', 'Old Wyrm' => 'Reprint'], $this->raritiesOf($this->gallery));
        // The main set is left alone.
        self::assertSame(['Ember Wyrm' => 'Rare'], $this->raritiesOf($this->main));
        // A new rarity of the game, ranked after the others.
        $reprint = $this->em->getRepository(Rarity::class)->findOneBy(['game' => $this->game->getId(), 'name' => 'Reprint']);
        self::assertSame(5, $reprint?->getSortOrder());

        // What it is for: a line of its own among the pull rates of the main set.
        $this->client->request('GET', '/api/admin/sets/'.$this->main->getId().'/pull-rates', server: $this->authorization($this->createAdmin()));
        self::assertSame(['Rare' => 1, 'Reprint' => 2], array_column($this->responseBody()['rarities'], 'cardsInSet', 'name'));

        // The sub-set says which rarity it gives.
        $this->client->request('GET', '/api/admin/sets/'.$this->gallery->getId().'/pull-rates', server: $this->authorization($this->createAdmin()));
        self::assertSame('Reprint', $this->responseBody()['forcedRarity']);
    }

    public function testUsesARarityTheGameAlreadyHasRatherThanATwin(): void
    {
        $this->force($this->gallery, 'Rare');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->em->getRepository(Rarity::class)->count(['game' => $this->game->getId()]));
        self::assertSame(['Old Charm' => 'Rare', 'Old Wyrm' => 'Rare'], $this->raritiesOf($this->gallery));
    }

    /**
     * The source is right about everything else, and would otherwise undo
     * the choice at every import.
     */
    public function testAnImportKeepsTheForcedRarityThenTheSourceTakesOverAgain(): void
    {
        $this->force($this->gallery, 'Reprint');
        $record = [
            'game' => ['slug' => $this->game->getSlug(), 'name' => $this->game->getName()],
            'set' => ['code' => 'MAIN-G', 'name' => 'Main Set Gallery'],
            'number' => '001',
            'name' => 'Old Wyrm',
            'rarity' => 'Rare',
        ];
        $import = function (array $record): void {
            $importer = static::getContainer()->get(CardImporter::class);
            $importer->import(static::getContainer()->get(ImportedCardFactory::class)->fromArray($record));
            // A card the source adds to the set later.
            $importer->import(static::getContainer()->get(ImportedCardFactory::class)->fromArray(['number' => '003', 'name' => 'New Wyrm'] + $record));
            $importer->flush();
        };

        $import($record);
        self::assertSame(['New Wyrm' => 'Reprint', 'Old Charm' => 'Reprint', 'Old Wyrm' => 'Reprint'], $this->raritiesOf($this->gallery));

        // Handed back to the source: nothing changes until the set is imported again.
        $this->force($this->gallery, null);
        self::assertResponseIsSuccessful();
        self::assertSame(['forcedRarity' => null], $this->responseBody());
        self::assertSame('Reprint', $this->raritiesOf($this->gallery)['Old Wyrm']);

        $import($record);
        self::assertSame(['New Wyrm' => 'Rare', 'Old Charm' => 'Reprint', 'Old Wyrm' => 'Rare'], $this->raritiesOf($this->gallery));
    }

    /**
     * One rarity for a whole set with boosters of its own would erase what
     * tells its cards apart.
     */
    public function testRefusesToForceARarityOnASetThatStandsOnItsOwn(): void
    {
        $this->force($this->main, 'Reprint');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Ember Wyrm' => 'Rare'], $this->raritiesOf($this->main));
    }

    public function testRefusesANameTooLongForARarity(): void
    {
        $this->force($this->gallery, str_repeat('a', 101));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Rare', $this->raritiesOf($this->gallery)['Old Wyrm']);
    }

    private function force(CardSet $set, ?string $name): void
    {
        $this->client->jsonRequest('PUT', '/api/admin/sets/'.$set->getId().'/forced-rarity', ['name' => $name], $this->authorization($this->createAdmin()));
    }

    /**
     * @return array<string, ?string> rarity name by card name, as the database has them
     */
    private function raritiesOf(CardSet $set): array
    {
        $this->em->clear();

        $rarities = [];
        foreach ($this->em->getRepository(Card::class)->findBy(['cardSet' => $set->getId()]) as $card) {
            $rarities[$card->getName()] = $card->getRarity()?->getName();
        }
        ksort($rarities);

        return $rarities;
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
}
