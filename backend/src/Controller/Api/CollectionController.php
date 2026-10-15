<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CardIdentityDto;
use App\Dto\CardSearchQuery;
use App\Dto\CardSummaryDto;
use App\Dto\CollectionCompletionDto;
use App\Dto\CollectionEntryDto;
use App\Dto\IdentitySearchQuery;
use App\Dto\OwnedCardDto;
use App\Dto\OwnedCardRequest;
use App\Dto\PaginatedDto;
use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\OwnedCard;
use App\Entity\User;
use App\Image\CardImageCache;
use App\Image\CardImages;
use App\Enum\CardFinish;
use App\Exception\CollectionEntryConflictException;
use App\Exception\FinishNotAvailableException;
use App\Repository\CardIdentityRepository;
use App\Repository\OwnedCardRepository;
use App\Service\CardIdentitySummaryService;
use App\Service\CollectionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The signed-in user's own collection. The owner always comes from the access
 * token, never from the URL or the body: no route here accepts a user id, so
 * there is nothing a client could change to reach someone else's cards.
 */
final class CollectionController
{
    public function __construct(
        private readonly OwnedCardRepository $ownedCardRepository,
        private readonly CardIdentityRepository $cardIdentityRepository,
        private readonly CollectionService $collectionService,
        private readonly CardIdentitySummaryService $cardIdentitySummaryService,
        private readonly CardImageCache $cardImageCache,
        private readonly CardImages $cardImages,
    ) {
    }

    #[Route('/api/collection', name: 'collection_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CardSearchQuery $query = new CardSearchQuery(),
    ): JsonResponse {
        $result = $this->ownedCardRepository->searchByUser($user, $query);

        return new JsonResponse(new PaginatedDto(
            array_map(
                fn (array $item): CollectionEntryDto => CollectionEntryDto::fromEntities($item['card'], $item['ownedCards'], $this->cardImages),
                $result['items'],
            ),
            $result['total'],
            $query->page,
            $query->limit,
        ));
    }

    /**
     * The cards of a catalog search the user does not own yet. Same parameters
     * and same answer shape as GET /api/cards.
     */
    #[Route('/api/collection/missing', name: 'collection_missing', methods: ['GET'])]
    public function missing(
        #[CurrentUser] User $user,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CardSearchQuery $query = new CardSearchQuery(),
    ): JsonResponse {
        $result = $this->ownedCardRepository->searchMissingByUser($user, $query);

        return new JsonResponse(new PaginatedDto(
            array_map(fn (Card $card): CardSummaryDto => CardSummaryDto::fromEntity($card, $this->cardImages), $result['items']),
            $result['total'],
            $query->page,
            $query->limit,
        ));
    }

    /**
     * How much of a catalog search the user owns. Takes the same parameters as
     * GET /api/cards, which stays public and the same for everyone: what
     * depends on the user is asked here, next to it.
     */
    #[Route('/api/collection/completion', name: 'collection_completion', methods: ['GET'])]
    public function completion(
        #[CurrentUser] User $user,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CardSearchQuery $query = new CardSearchQuery(),
    ): JsonResponse {
        $completion = $this->ownedCardRepository->completionByUser($user, $query);

        return new JsonResponse(CollectionCompletionDto::fromEntities($completion['total'], $completion['owned'], $completion['ownedOnPage']));
    }

    /**
     * What the user owns of a page of the grouped catalog. Takes the
     * parameters of GET /api/identities, which stays public and the same for
     * everyone, and is asked next to it.
     */
    #[Route('/api/collection/identities', name: 'collection_identities', methods: ['GET'])]
    public function identities(
        #[CurrentUser] User $user,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        IdentitySearchQuery $query = new IdentitySearchQuery(),
    ): JsonResponse {
        $identities = $this->cardIdentityRepository->search($query)['items'];

        return new JsonResponse([
            // Over the whole search: how many identities there are, and how
            // many of them the user owns at least one card of.
            'totalIdentities' => $this->cardIdentityRepository->countSearch($query),
            'startedIdentities' => $this->ownedCardRepository->countIdentitiesStartedByUser($user, $query),
            // For the requested page only, by identity id.
            // Always a JSON object, even empty: PHP would write [] otherwise.
            'ownedByIdentity' => (object) $this->ownedCardRepository->countOwnedByUserAndIdentity($user, $identities),
            // A picture the user owns, to show in place of the one everyone gets.
            'ownedImageByIdentity' => (object) array_map(
                $this->cardImageCache->addressOf(...),
                $this->ownedCardRepository->findOwnedImageUrlsByUserAndIdentity($user, $identities),
            ),
            'ownedWithoutIdentity' => null === ($others = $query->cardsWithoutIdentity()) ? 0 : $this->ownedCardRepository->countOwnedByUser($user, $others),
        ]);
    }

    /**
     * The identities of a search the user has no card of their own for: what
     * to print a stand-in of. Same parameters and same entries as
     * GET /api/identities.
     */
    #[Route('/api/collection/identities/missing', name: 'collection_identities_missing', methods: ['GET'])]
    public function missingIdentities(
        #[CurrentUser] User $user,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        IdentitySearchQuery $query = new IdentitySearchQuery(),
    ): JsonResponse {
        $result = $this->ownedCardRepository->searchIdentitiesMissingByUser($user, $query);

        return new JsonResponse(new PaginatedDto(
            $this->cardIdentitySummaryService->summarize($result['items']),
            $result['total'],
            $query->page,
            $query->limit,
        ));
    }

    /**
     * The user's copies of one card, one entry per language and finish. An empty list
     * means the card exists but is not owned.
     */
    #[Route('/api/collection/cards/{id}', name: 'collection_card_show', methods: ['GET'])]
    public function showCard(Card $card, #[CurrentUser] User $user): JsonResponse
    {
        $ownedCards = $this->ownedCardRepository->findByUserAndCard($user, $card);

        return new JsonResponse(['data' => array_map(OwnedCardDto::fromEntity(...), $ownedCards)]);
    }

    /**
     * PUT rather than POST: (user, card, language, finish) already identifies
     * the entry, so sending the same request twice cannot create a duplicate.
     */
    #[Route('/api/collection/cards/{id}/{language}/{finish}', name: 'collection_card_put', methods: ['PUT'], format: 'json')]
    public function putCard(
        Card $card,
        string $language,
        string $finish,
        #[MapRequestPayload(acceptFormat: 'json')] OwnedCardRequest $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $this->assertLanguageCode($language);

        try {
            $result = $this->collectionService->setOwnedCard($user, $card, $language, $this->finish($finish), $request->quantity, $request->condition);
        } catch (CollectionEntryConflictException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        } catch (FinishNotAvailableException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse(
            [
                ...OwnedCardDto::fromEntity($result['ownedCard'])->jsonSerialize(),
                'discovery' => $this->discovery($user, $card, $result['newIdentities']),
            ],
            $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    /**
     * What adding this card started: the identities the user had no card of
     * until now, and how far that takes them through those of the game.
     * Null for the usual case, where it started nothing.
     *
     * @param list<CardIdentity> $newIdentities
     *
     * @return array<string, mixed>|null
     */
    private function discovery(User $user, Card $card, array $newIdentities): ?array
    {
        if ([] === $newIdentities) {
            return null;
        }

        $game = $card->getCardSet()->getGame();
        $identitiesOfTheGame = new IdentitySearchQuery(game: $game->getSlug());

        return [
            'identities' => array_map(CardIdentityDto::fromEntity(...), $newIdentities),
            // What the game calls its identities, when it says.
            'label' => $game->getIdentityLabel(),
            'started' => $this->ownedCardRepository->countIdentitiesStartedByUser($user, $identitiesOfTheGame),
            'total' => $this->cardIdentityRepository->countSearch($identitiesOfTheGame),
        ];
    }

    /**
     * Answers 204 whether or not the card was owned: the outcome the client
     * asked for holds either way.
     */
    #[Route('/api/collection/cards/{id}/{language}/{finish}', name: 'collection_card_delete', methods: ['DELETE'])]
    public function deleteCard(Card $card, string $language, string $finish, #[CurrentUser] User $user): Response
    {
        $this->assertLanguageCode($language);

        $this->collectionService->removeOwnedCard($user, $card, $language, $this->finish($finish));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function finish(string $finish): CardFinish
    {
        return CardFinish::tryFrom($finish)
            ?? throw new UnprocessableEntityHttpException(sprintf('Finish must be one of: %s.', implode(', ', array_column(CardFinish::cases(), 'value'))));
    }

    private function assertLanguageCode(string $language): void
    {
        if (1 !== preg_match(OwnedCard::LANGUAGE_PATTERN, $language)) {
            throw new UnprocessableEntityHttpException('Language must be a two-letter ISO 639-1 code, in lower case.');
        }
    }
}
