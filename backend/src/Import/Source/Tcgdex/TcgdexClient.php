<?php

declare(strict_types=1);

namespace App\Import\Source\Tcgdex;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the TCGdex API (https://tcgdex.dev), and only does that: what the
 * answers mean for the catalog is TcgdexCardMapper's job.
 *
 * The details of a card are only served one card at a time by the REST API,
 * which would take one request per card. The GraphQL API returns them a
 * thousand at a time: a set costs two requests instead of a few hundred.
 */
final class TcgdexClient
{
    /** The catalog has one name per card: this is the language it is in. */
    public const string LANGUAGE = 'fr';

    /** What TCGdex calls the category of creature cards, in that language. */
    private const string CREATURE_CATEGORY = 'Pokémon';

    /** The largest page the API was seen to serve. */
    private const int PAGE_SIZE = 1000;

    /** A safety net: no listing is that long, so reaching it means the API never said "no more". */
    private const int MAX_PAGES = 100;

    private const string CARDS_QUERY = <<<'GRAPHQL'
        query ($filters: CardsFilters, $pagination: Pagination) {
          cards(filters: $filters, pagination: $pagination) @locale(lang: "%s") {
            %s
          }
        }
        GRAPHQL;

    private ?\DateTimeImmutable $lastRequestAt = null;

    public function __construct(
        // Configured in config/packages/http_client.yaml: address, timeouts, retries.
        #[Target('tcgdex.client')]
        private readonly HttpClientInterface $httpClient,
        private readonly ClockInterface $clock,
        /** At most four requests a second, one at a time. */
        private readonly int $pauseMilliseconds = 250,
    ) {
    }

    /**
     * @return list<string> the ids of the sets that exist in the catalog's language
     */
    public function fetchSetIds(): array
    {
        $ids = [];
        foreach ($this->getJson(sprintf('/v2/%s/sets', self::LANGUAGE)) as $set) {
            if (\is_array($set) && \is_string($set['id'] ?? null)) {
                $ids[] = $set['id'];
            }
        }

        return $ids;
    }

    /**
     * A set without its cards: its name, release date and number of cards.
     *
     * @return array<string, mixed>
     *
     * @throws TcgdexException also when the set does not exist
     */
    public function fetchSet(string $setId): array
    {
        $set = $this->getJson(sprintf('/v2/%s/sets/%s', self::LANGUAGE, rawurlencode($setId)));
        unset($set['cards']);

        /** @var array<string, mixed> $set */
        return $set;
    }

    /**
     * The cards of a set, with the details the catalog keeps.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchCards(string $setId): array
    {
        // The API has no filter on the set of a card, only on a part of the
        // card id, which starts with the set id. "sv1-" could also be found
        // inside another id: what does not belong to the set is dropped.
        $cards = $this->fetchAllCards(['id' => $setId.'-'], 'id localId name rarity category dexId types hp stage image set { id }');

        return array_values(array_filter(
            $cards,
            static fn (array $card): bool => \is_array($card['set'] ?? null) && $setId === ($card['set']['id'] ?? null),
        ));
    }

    /**
     * The name and species numbers of every creature card, all sets
     * together: what TcgdexCardMapper names the species from.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchCreatureNames(): array
    {
        return $this->fetchAllCards(['category' => self::CREATURE_CATEGORY], 'name dexId');
    }

    /**
     * @param array<string, string> $filters never empty: without any filter, the API answers with an error
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAllCards(array $filters, string $fields): array
    {
        $cards = [];

        for ($page = 1; $page <= self::MAX_PAGES; ++$page) {
            $answer = $this->request('POST', '/v2/graphql', ['json' => [
                'query' => sprintf(self::CARDS_QUERY, self::LANGUAGE, $fields),
                'variables' => ['filters' => $filters, 'pagination' => ['page' => $page, 'itemsPerPage' => self::PAGE_SIZE]],
            ]]);

            // GraphQL answers 200 even when the query failed.
            if (isset($answer['errors']) || !\is_array($answer['data']['cards'] ?? null)) {
                throw new TcgdexException(sprintf('TCGdex could not list the cards: %s', $this->firstError($answer)));
            }

            $pageOfCards = array_values(array_filter($answer['data']['cards'], \is_array(...)));
            array_push($cards, ...$pageOfCards);

            // A page that is not full is the last one.
            if (\count($answer['data']['cards']) < self::PAGE_SIZE) {
                return $cards;
            }
        }

        throw new TcgdexException('TCGdex keeps returning full pages of cards: giving up.');
    }

    /**
     * @return array<mixed>
     */
    private function getJson(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $this->waitForTurn();

        try {
            // toArray() throws on a 4xx or 5xx answer, and on a body that is not JSON.
            return $this->httpClient->request($method, $path, $options)->toArray();
        } catch (ExceptionInterface $exception) {
            throw new TcgdexException(sprintf('TCGdex did not answer "%s %s": %s', $method, $path, $exception->getMessage()), previous: $exception);
        } finally {
            $this->lastRequestAt = $this->clock->now();
        }
    }

    /**
     * Leaves a pause after the previous request, so the import never sends
     * more than a few requests a second.
     */
    private function waitForTurn(): void
    {
        if (null === $this->lastRequestAt || $this->pauseMilliseconds <= 0) {
            return;
        }

        $elapsed = (float) $this->clock->now()->format('U.u') - (float) $this->lastRequestAt->format('U.u');
        $remaining = $this->pauseMilliseconds / 1000 - $elapsed;

        if ($remaining > 0) {
            $this->clock->sleep($remaining);
        }
    }

    /**
     * @param array<mixed> $answer
     */
    private function firstError(array $answer): string
    {
        $message = $answer['errors'][0]['message'] ?? null;

        return \is_string($message) ? $message : 'unexpected answer.';
    }
}
