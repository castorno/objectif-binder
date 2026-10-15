<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CardIdentitySummaryDto;
use App\Entity\CardIdentity;
use App\Image\RemoteImageCache;
use App\Repository\CardIdentityRepository;

/**
 * Turns identities into entries of the grouped catalog. Every list of
 * identities goes through here, so they all count the cards and pick the
 * picture of an entry the same way.
 */
final class CardIdentitySummaryService
{
    public function __construct(
        private readonly CardIdentityRepository $cardIdentityRepository,
        private readonly RemoteImageCache $remoteImageCache,
    ) {
    }

    /**
     * Two queries whatever the number of identities.
     *
     * @param list<CardIdentity> $identities
     *
     * @return list<CardIdentitySummaryDto>
     */
    public function summarize(array $identities): array
    {
        $cardCounts = $this->cardIdentityRepository->countCardsByIdentity($identities);
        $imageUrls = $this->cardIdentityRepository->findImageUrlsByIdentity($identities);

        return array_map(
            fn (CardIdentity $identity): CardIdentitySummaryDto => CardIdentitySummaryDto::fromEntity(
                $identity,
                $cardCounts[(string) $identity->getId()] ?? 0,
                $this->remoteImageCache->addressOf($imageUrls[(string) $identity->getId()] ?? null),
                $this->remoteImageCache->addressOf($identity->getImageUrl()),
            ),
            $identities,
        );
    }
}
