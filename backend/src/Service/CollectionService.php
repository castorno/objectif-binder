<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Card;
use App\Entity\OwnedCard;
use App\Entity\User;
use App\Enum\CardCondition;
use App\Exception\CollectionEntryConflictException;
use App\Repository\OwnedCardRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class CollectionService
{
    public function __construct(
        private readonly OwnedCardRepository $ownedCardRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Records that the user owns this card in this language, creating the
     * entry or replacing its quantity and condition. Calling it twice with the
     * same arguments leaves the collection as calling it once.
     *
     * @return array{ownedCard: OwnedCard, created: bool}
     *
     * @throws CollectionEntryConflictException
     */
    public function setOwnedCard(User $user, Card $card, string $language, int $quantity, ?CardCondition $condition): array
    {
        $ownedCard = $this->ownedCardRepository->findOneByUserCardAndLanguage($user, $card, $language);
        $created = null === $ownedCard;

        if ($created) {
            $ownedCard = new OwnedCard($user, $card, $language, $quantity);
            $this->entityManager->persist($ownedCard);
        } else {
            $ownedCard->setQuantity($quantity);
        }
        $ownedCard->setCondition($condition);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            // Two requests created the same entry at the same moment and the
            // other one won: the unique index kept the collection consistent.
            throw new CollectionEntryConflictException($exception);
        }

        return ['ownedCard' => $ownedCard, 'created' => $created];
    }

    /**
     * Does nothing when the user does not own the card in this language.
     */
    public function removeOwnedCard(User $user, Card $card, string $language): void
    {
        $ownedCard = $this->ownedCardRepository->findOneByUserCardAndLanguage($user, $card, $language);
        if (null === $ownedCard) {
            return;
        }

        $this->entityManager->remove($ownedCard);
        $this->entityManager->flush();
    }
}
