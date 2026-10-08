<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\PullRate;
use App\Entity\Rarity;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seeds a small, entirely fictional card game so the application is usable
 * right after `docker compose up`, without shipping any third-party data.
 *
 * Deliberately a plain command rather than doctrine-fixtures: it only ever
 * adds its own game and never purges existing tables.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Insert a fictional demo game (sets, rarities, cards, identities, pull rates)')]
final class SeedDemoDataCommand
{
    public const string GAME_SLUG = 'lumenfall';

    /** What the demo game calls its card identities: its recurring creatures. */
    private const string IDENTITY_LABEL = 'Créatures';

    private const string IDENTITY_TYPE = 'Créature';

    private const int CARDS_PER_SET = 60;

    /**
     * Rarity name => [sort order, number of cards per set, booster odds "1 in N" or null when unknown].
     * Card numbers are assigned in this order, commons first.
     */
    private const array RARITIES = [
        'Commune' => [1, 24, null],
        'Peu commune' => [2, 16, null],
        'Rare' => [3, 9, 3],
        'Épique' => [4, 5, 12],
        'Légendaire' => [5, 3, 36],
        'Or' => [6, 3, 51],
    ];

    private const array SETS = [
        [
            'name' => 'Aube des Braises',
            'code' => 'ADB',
            'releaseDate' => '2025-03-14',
            'element' => 'Feu',
            'epithets' => ['des Braises', 'du Brasier', 'de Cendre', 'du Zénith', 'des Forges', 'de l\'Aurore'],
        ],
        [
            'name' => 'Marées d\'Obsidienne',
            'code' => 'MDO',
            'releaseDate' => '2025-09-26',
            'element' => 'Eau',
            'epithets' => ['des Abysses', 'd\'Obsidienne', 'des Marées', 'du Récif', 'de l\'Écume', 'des Profondeurs'],
        ],
    ];

    private const array NOUNS = [
        ['Sentinelle', 'Créature'],
        ['Golem', 'Créature'],
        ['Oracle', 'Créature'],
        ['Wyrm', 'Créature'],
        ['Éclaireuse', 'Créature'],
        ['Colosse', 'Créature'],
        ['Rituel', 'Sort'],
        ['Serment', 'Sort'],
        ['Lanterne', 'Relique'],
        ['Couronne', 'Relique'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GameRepository $gameRepository,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $existingGame = $this->gameRepository->findOneBy(['slug' => self::GAME_SLUG]);
        if (null !== $existingGame) {
            // A demo game seeded before identities existed gets them now,
            // without touching its cards or anything users attached to them.
            if (null === $existingGame->getIdentityLabel()) {
                $linkedCards = $this->addIdentitiesToExistingCards($existingGame);
                $this->em->flush();
                $io->success(sprintf('Demo game already present: identities added to %d of its cards.', $linkedCards));

                return Command::SUCCESS;
            }

            $io->note('Demo game already present, nothing to do.');

            return Command::SUCCESS;
        }

        $game = new Game('Lumenfall', self::GAME_SLUG);
        $this->em->persist($game);
        $identities = $this->createIdentities($game);

        $rarities = [];
        foreach (self::RARITIES as $name => [$sortOrder]) {
            $rarities[$name] = new Rarity($game, $name, $sortOrder);
            $this->em->persist($rarities[$name]);
        }

        foreach (self::SETS as $setData) {
            $set = new CardSet($game, $setData['name'], $setData['code']);
            $set->setReleaseDate(new \DateTimeImmutable($setData['releaseDate']));
            $this->em->persist($set);

            $number = 0;
            foreach (self::RARITIES as $rarityName => [, $cardCount, $oddsOneIn]) {
                for ($i = 0; $i < $cardCount; ++$i) {
                    $this->em->persist($this->buildCard($set, $setData, ++$number, $rarities[$rarityName], $identities));
                }

                if (null !== $oddsOneIn) {
                    $this->em->persist(new PullRate($set, $rarities[$rarityName], $oddsOneIn));
                }
            }
        }

        $this->em->flush();

        $io->success(sprintf('Demo game "%s" created: %d sets, %d cards.', $game->getName(), count(self::SETS), count(self::SETS) * self::CARDS_PER_SET));

        return Command::SUCCESS;
    }

    /**
     * One identity per creature: each comes back under several names in every
     * set. Spells and relics are left without one, as some cards of real
     * games are.
     *
     * @return array<string, CardIdentity> by creature noun
     */
    private function createIdentities(Game $game): array
    {
        $game->setIdentityLabel(self::IDENTITY_LABEL);

        $identities = [];
        $order = 0;
        foreach (self::NOUNS as [$noun, $type]) {
            if (self::IDENTITY_TYPE !== $type) {
                continue;
            }

            $identities[$noun] = new CardIdentity($game, $noun, sprintf('demo-identity-%02d', ++$order))->setSortOrder($order);
            $this->em->persist($identities[$noun]);
        }

        return $identities;
    }

    /**
     * @return int the number of cards given an identity
     */
    private function addIdentitiesToExistingCards(Game $game): int
    {
        $identities = $this->createIdentities($game);
        /** @var list<Card> $cards */
        $cards = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Card::class, 'c')
            ->join('c.cardSet', 's')
            ->where('s.game = :game')
            ->setParameter('game', $game)
            ->getQuery()
            ->getResult();

        $linkedCards = 0;
        foreach ($cards as $card) {
            // Demo cards are named "<noun> <epithet>".
            $identity = $identities[strstr($card->getName(), ' ', true) ?: ''] ?? null;
            if (null !== $identity) {
                $card->addIdentity($identity);
                ++$linkedCards;
            }
        }

        return $linkedCards;
    }

    /**
     * @param array{code: string, element: string, epithets: list<string>} $setData
     * @param array<string, CardIdentity>                                  $identities by creature noun
     */
    private function buildCard(CardSet $set, array $setData, int $number, Rarity $rarity, array $identities): Card
    {
        // 7 is coprime with 60: walks every (noun, epithet) pair exactly once
        // while keeping neighbouring card numbers from sharing a noun.
        $pairIndex = ($number * 7) % self::CARDS_PER_SET;
        [$noun, $type] = self::NOUNS[$pairIndex % count(self::NOUNS)];
        $epithet = $setData['epithets'][intdiv($pairIndex, count(self::NOUNS))];

        $attributes = [
            'type' => $type,
            'element' => $setData['element'],
            'cost' => 1 + $pairIndex % 7,
        ];
        if ('Créature' === $type) {
            $attributes['power'] = 1 + ($pairIndex * 3) % 9;
        }

        $card = new Card($set, $noun.' '.$epithet, sprintf('%03d', $number))
            ->setRarity($rarity)
            ->setExternalId(sprintf('demo-%s-%03d', strtolower($setData['code']), $number))
            ->setAttributes($attributes);

        if (isset($identities[$noun])) {
            $card->addIdentity($identities[$noun]);
        }

        return $card;
    }
}
