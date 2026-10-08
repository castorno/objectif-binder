<?php

declare(strict_types=1);

namespace App\Import\Source\Tcgdex;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Downloads sets from TCGdex into files of the import format, one file per
 * set. Fetching and importing are two steps: this one needs the network and
 * writes no card, app:import writes cards and needs no network.
 *
 * A set already downloaded is not asked for again, so a download stopped
 * half-way resumes where it was.
 */
final class TcgdexFetcher
{
    private const string SPECIES_FILE = '_species.json';

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
            return new TcgdexFetchResult($setId, $path, downloaded: false);
        }

        $set = $this->client->fetchSet($setId);
        $expected = \is_int($set['localCardCount'] ?? null) ? $set['localCardCount'] : null;

        // Never released in the catalog's language: not a failure, and not
        // worth asking for its cards.
        if (0 === $expected) {
            $this->importLogger->info('Set skipped: no card in the catalog language.', ['set' => $setId]);

            return new TcgdexFetchResult($setId, $path, downloaded: false, expectedCardCount: 0, empty: true);
        }

        $speciesNames = $this->speciesNames($refresh);
        $cards = $this->client->fetchCards($setId);

        // The set lists cards, and none came back: that is an anomaly.
        if ([] === $cards) {
            throw new TcgdexException(sprintf('TCGdex returned no card for the set "%s".', $setId));
        }

        $lines = '';
        foreach ($cards as $card) {
            $lines .= json_encode($this->mapper->toRecord($set, $card, $speciesNames, $withImages), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)."\n";
        }

        // Written under another name, then renamed: a file with the final
        // name is always a whole set, never the start of one.
        $this->filesystem->dumpFile($path, $lines);

        $result = new TcgdexFetchResult($setId, $path, downloaded: true, cardCount: \count($cards), expectedCardCount: $expected);

        $this->importLogger->log($result->isComplete() ? 'info' : 'warning', 'Set fetched from TCGdex.', [
            'set' => $setId,
            'cards' => $result->cardCount,
            'expectedCards' => $result->expectedCardCount,
        ]);

        return $result;
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
