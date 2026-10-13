<?php

declare(strict_types=1);

namespace App\Import\Source\Tcgdex;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Downloads sets from TCGdex into files of the import format, one file per
 * set. A card comes in the catalog's language when TCGdex has it there, and
 * in the fallback language otherwise. Fetching and importing are two steps: this one needs the network and
 * writes no card, app:import writes cards and needs no network.
 *
 * A set already downloaded is not asked for again, so a download stopped
 * half-way resumes where it was.
 */
final class TcgdexFetcher
{
    private const string SPECIES_FILE = '_species.json';

    /**
     * Series that are not made of physical cards: the cards of the mobile
     * game cannot be owned, sorted in a binder or missing from one.
     */
    private const array DIGITAL_SERIES = ['tcgp'];

    /** @var array<int, string>|null */
    private ?array $speciesNames = null;

    public function __construct(
        private readonly TcgdexClient $client,
        private readonly TcgdexCardMapper $mapper,
        private readonly Filesystem $filesystem,
        // Outside Git: see the root .gitignore.
        #[Autowire('%kernel.project_dir%/var/import/tcgdex')]
        private readonly string $directory,
        #[Target('import')]
        private readonly LoggerInterface $importLogger,
    ) {
    }

    /**
     * @return list<string>
     *
     * @throws TcgdexException
     */
    public function availableSetIds(): array
    {
        return $this->client->fetchSetIds();
    }

    /**
     * @param bool $refresh    download again what is already there
     * @param bool $withImages also keep the address of each card's picture (see TcgdexCardMapper)
     *
     * @throws TcgdexException
     */
    public function fetchSet(string $setId, bool $refresh = false, bool $withImages = false): TcgdexFetchResult
    {
        // A set id ends up in a file name: nothing but what TCGdex uses.
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $setId)) {
            throw new TcgdexException(sprintf('"%s" is not a set id.', $setId));
        }

        $path = $this->directory.'/'.$setId.'.jsonl';
        if (!$refresh && is_file($path)) {
            return new TcgdexFetchResult($setId, TcgdexFetchStatus::AlreadyDownloaded, $path);
        }

        $local = $this->client->fetchSet($setId);
        if (null !== $local && $this->isDigital($local)) {
            return $this->digital($setId, $path);
        }

        // A set can be missing from the catalog's language, wholly or in
        // part: what the fallback language has fills the gap.
        $fallback = $this->client->fetchSet($setId, TcgdexClient::FALLBACK_LANGUAGE);
        $set = $local ?? $fallback;
        if (null === $set) {
            throw new TcgdexException(sprintf('TCGdex knows no set "%s".', $setId));
        }
        if ($this->isDigital($set)) {
            return $this->digital($setId, $path);
        }

        /** @var list<string> $localIds */
        $localIds = $local['cardIds'] ?? [];
        /** @var list<string> $fallbackIds */
        $fallbackIds = $fallback['cardIds'] ?? [];
        $fallbackOnlyIds = array_diff($fallbackIds, $localIds);
        $expected = \count($localIds) + \count($fallbackOnlyIds);

        // Not a failure, and not worth asking for its cards.
        if (0 === $expected) {
            $this->importLogger->info('Set skipped: it lists no card.', ['set' => $setId]);

            return new TcgdexFetchResult($setId, TcgdexFetchStatus::Empty, $path, expectedCardCount: 0);
        }

        $speciesNames = $this->speciesNames($refresh);
        $localCards = [] === $localIds ? [] : $this->client->fetchCards($setId);

        // The cards of the fallback language cost a request: only asked for
        // when one of them is missing from the catalog's language, or has a
        // picture that a card of the catalog's language lacks.
        $hasPicture = static fn (array $card): bool => \is_string($card['image'] ?? null);
        $fallbackCards = [];
        if ([] !== $fallbackOnlyIds || ($withImages && [] !== $fallbackIds && !array_all($localCards, $hasPicture))) {
            $fallbackCards = array_column($this->client->fetchCards($setId, TcgdexClient::FALLBACK_LANGUAGE), null, 'id');
        }

        $lines = '';
        $cardCount = 0;
        foreach ($localCards as $card) {
            $id = $card['id'] ?? null;
            $languages = [TcgdexClient::LANGUAGE];
            if (\in_array($id, $fallbackIds, true)) {
                $languages[] = TcgdexClient::FALLBACK_LANGUAGE;
            }

            // The same card, with its text in another language: scans of a
            // translated set often come later than the English ones, or never.
            if ($withImages && !$hasPicture($card) && \is_string($id) && $hasPicture($fallbackCards[$id] ?? [])) {
                $card['image'] = $fallbackCards[$id]['image'];
            }

            $lines .= $this->line($this->mapper->toRecord($set, $card, $speciesNames, $withImages, $languages));
            ++$cardCount;
            unset($fallbackCards[$id]);
        }

        // What is left was never printed in the catalog's language.
        foreach ($fallbackCards as $card) {
            $lines .= $this->line($this->mapper->toRecord($set, $card, $speciesNames, $withImages, [TcgdexClient::FALLBACK_LANGUAGE]));
            ++$cardCount;
        }

        // The set lists cards, and none came back: that is an anomaly.
        if (0 === $cardCount) {
            throw new TcgdexException(sprintf('TCGdex returned no card for the set "%s".', $setId));
        }

        // Written under another name, then renamed: a file with the final
        // name is always a whole set, never the start of one.
        $this->filesystem->dumpFile($path, $lines);

        $result = new TcgdexFetchResult($setId, TcgdexFetchStatus::Downloaded, $path, cardCount: $cardCount, expectedCardCount: $expected);

        $this->importLogger->log($result->isComplete() ? 'info' : 'warning', 'Set fetched from TCGdex.', [
            'set' => $setId,
            'cards' => $result->cardCount,
            'expectedCards' => $result->expectedCardCount,
        ]);

        return $result;
    }

    /**
     * @param array<string, mixed> $set
     */
    private function isDigital(array $set): bool
    {
        return \is_array($set['serie'] ?? null) && \in_array($set['serie']['id'] ?? null, self::DIGITAL_SERIES, true);
    }

    private function digital(string $setId, string $path): TcgdexFetchResult
    {
        $this->importLogger->info('Set skipped: not physical cards.', ['set' => $setId]);

        return new TcgdexFetchResult($setId, TcgdexFetchStatus::Digital, $path);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function line(array $record): string
    {
        return json_encode($record, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)."\n";
    }

    /**
     * The names of the species, kept in a file next to the sets: building
     * them takes a listing of every creature card, done once rather than
     * for each set.
     *
     * @return array<int, string>
     */
    private function speciesNames(bool $refresh): array
    {
        if (null !== $this->speciesNames) {
            return $this->speciesNames;
        }

        $path = $this->directory.'/'.self::SPECIES_FILE;

        if (!$refresh && is_file($path)) {
            $names = json_decode((string) file_get_contents($path), true);
            if (\is_array($names) && [] !== $names) {
                /** @var array<int, string> $names */
                return $this->speciesNames = $names;
            }
        }

        $names = $this->mapper->speciesNames($this->client->fetchCreatureNames());
        $this->filesystem->dumpFile($path, json_encode($names, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT));
        $this->importLogger->info('Species names fetched from TCGdex.', ['species' => \count($names)]);

        return $this->speciesNames = $names;
    }
}
