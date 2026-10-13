import { FILTER_LANGUAGES } from '../collection/languages'
import { useQuery } from '@tanstack/react-query'
import { useEffect, useId, useState } from 'react'
import { gameIdentityGroupsQuery, gameRaritiesQuery, gameSetsQuery, gamesQuery } from '../../api/queries'
import type { CardSearchFilters } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { ComboboxField } from '../../components/ComboboxField'
import { SelectField } from '../../components/SelectField'
import { setOptions } from './setOptions'
import { hasActiveFilters, parseOwnership } from './useCardSearchParams'

type CardFiltersProps = {
  filters: CardSearchFilters
  /** Text currently typed in the search field, possibly not applied to `filters.q` yet. */
  queryDraft: string
  onQueryDraftChange: (value: string) => void
  onChange: (patch: Partial<Omit<CardSearchFilters, 'page' | 'view'>>) => void
  onReset: () => void
  /** Offer to narrow the search to owned or missing cards: for a signed-in user only. */
  showOwnership?: boolean
  /** The grouped catalogue is searched by name and game only: identities have no set or rarity. */
  grouped?: boolean
}

/**
 * A crossed-out card, next to a set none of whose cards has a picture: its
 * cards will show generated stand-ins.
 */
function NoPictureMark() {
  return (
    <span title="Extension sans images" className="inline-block align-text-bottom text-muted">
      <svg
        aria-hidden="true"
        viewBox="0 0 16 16"
        className="h-4 w-4"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
      >
        <rect x="3.75" y="1.75" width="8.5" height="12.5" rx="1.5" />
        <path d="M1.5 14.5l13-13" />
      </svg>
      <span className="sr-only">sans images</span>
    </span>
  )
}

export function CardFilters({
  filters,
  queryDraft,
  onQueryDraftChange,
  onChange,
  onReset,
  showOwnership = false,
  grouped = false,
}: CardFiltersProps) {
  const searchId = useId()
  const hintId = useId()
  const moreFiltersId = useId()
  // On a phone the filters other than the name stay folded until asked for:
  // the cards come first.
  const [showsMoreFilters, setShowsMoreFilters] = useState(false)

  const games = useQuery(gamesQuery())
  const sets = useQuery({ ...gameSetsQuery(filters.game), enabled: filters.game !== '' && !grouped })
  // Only the rarities of the chosen set: the filter offers nothing that would find no card.
  const rarities = useQuery({
    ...gameRaritiesQuery(filters.game, filters.set),
    enabled: filters.game !== '' && !grouped,
  })
  // The chosen rarity does not exist in the set just picked: keeping it
  // would leave an empty page and a filter showing a value it no longer offers.
  const rarityIsGone =
    filters.rarity !== '' &&
    rarities.isSuccess &&
    !rarities.isPlaceholderData &&
    !rarities.data.some((rarity) => rarity.name === filters.rarity)
  useEffect(() => {
    if (rarityIsGone) onChange({ rarity: '' })
  }, [rarityIsGone, onChange])

  const groups = useQuery({ ...gameIdentityGroupsQuery(filters.game), enabled: filters.game !== '' && grouped })

  const failedQueries = [games, sets, rarities, groups].filter((query) => query.isError)
  // A game without any picture has nothing to tell apart: marking every
  // one of its sets would only be noise.
  const someSetsHavePictures = (sets.data ?? []).some((set) => set.hasPictures)

  // Offered only for a game that sorts its identities into groups, under the name the game gives them.
  const groupLabel = games.data?.find((game) => game.slug === filters.game)?.identityGroupLabel ?? 'Groupe'
  const showsGroups = grouped && (filters.group !== '' || (groups.data ?? []).length > 0)

  const hasGame = filters.game !== ''
  // A draft not yet sent to the URL counts too: it is about to become a filter.
  const canReset = hasActiveFilters(filters) || queryDraft !== ''
  // Told on the button that unfolds them: a folded filter still narrows the search.
  const foldedFilterCount = [filters.game, filters.group, filters.set, filters.rarity, filters.language, filters.ownership].filter(
    (value) => value !== '',
  ).length
  // Said by the fields themselves, where the eye already is.
  const needsGameLabel = 'Choisir un jeu'

  return (
    <form
      role="search"
      aria-label="Rechercher des cartes"
      onSubmit={(event) => event.preventDefault()}
    >
      <div
        className={`grid gap-3 sm:grid-cols-2 ${
          grouped
            ? showsGroups
              ? 'lg:grid-cols-[2fr_1fr_1fr]'
              : 'lg:grid-cols-[2fr_1fr]'
            : showOwnership
              ? 'lg:grid-cols-[2fr_1fr_1fr_1fr_1fr]'
              : 'lg:grid-cols-[2fr_1fr_1fr_1fr]'
        }`}
      >
        <div className="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
          <label htmlFor={searchId} className="text-sm font-medium">
            {grouped ? 'Nom' : 'Nom de la carte'}
          </label>
          <div className="flex gap-2">
            <input
              id={searchId}
              type="search"
              value={queryDraft}
              maxLength={100}
              placeholder="Ex. : Sentinelle"
              autoComplete="off"
              onChange={(event) => onQueryDraftChange(event.target.value)}
              // Enter applies the search right away instead of waiting for the
              // debounce. Handled here: a browser only submits a form on Enter
              // by itself when it has a single text field, and this one has two.
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault()
                  onChange({ q: queryDraft })
                }
              }}
              className="h-10 min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 text-sm placeholder:text-muted"
            />
            <button
              type="button"
              aria-expanded={showsMoreFilters}
              aria-controls={moreFiltersId}
              onClick={() => setShowsMoreFilters((shown) => !shown)}
              className={`${buttonStyles.secondary} h-10 shrink-0 sm:hidden`}
            >
              Filtres{foldedFilterCount > 0 && ` (${foldedFilterCount})`}
            </button>
          </div>
        </div>

        {/* `contents`: the fields stay cells of the grid above. */}
        <div id={moreFiltersId} className={showsMoreFilters ? 'contents' : 'hidden sm:contents'}>
          <SelectField
            label="Jeu"
            allLabel="Tous les jeux"
            value={filters.game}
            onChange={(game) => onChange({ game })}
            options={(games.data ?? []).map((game) => ({ value: game.slug, label: game.name }))}
          />
          {showsGroups && (
            <SelectField
              label={groupLabel}
              allLabel="Tout"
              value={filters.group}
              onChange={(group) => onChange({ group })}
              options={(groups.data ?? []).map((group) => ({
                value: group.name,
                label: `${group.name} (${group.identityCount})`,
              }))}
            />
          )}
          {!grouped && (
            <>
              {/* A game can have hundreds of sets: found by typing, not by scrolling. */}
              <ComboboxField
                label="Extension"
                allLabel={hasGame ? 'Toutes les extensions' : needsGameLabel}
                emptyMessage="Aucune extension ne correspond."
                value={filters.set}
                disabled={!hasGame}
                describedBy={hasGame ? undefined : hintId}
                onChange={(set) => onChange({ set })}
                options={setOptions(
                  // A sub-set comes with its main set, and is not offered apart.
                  // It stays in the list while it is the filter, which a link
                  // made earlier can still ask for, so that the field can name it.
                  (sets.data ?? []).filter((set) => set.parentCode === null || set.code === filters.set),
                  (set) => (someSetsHavePictures && !set.hasPictures ? <NoPictureMark /> : undefined),
                )}
              />
              <SelectField
                label="Rareté"
                allLabel={hasGame ? 'Toutes les raretés' : needsGameLabel}
                value={filters.rarity}
                disabled={!hasGame}
                describedBy={hasGame ? undefined : hintId}
                onChange={(rarity) => onChange({ rarity })}
                options={(rarities.data ?? []).map((rarity) => ({ value: rarity.name, label: rarity.name }))}
              />
              {/* Not tied to a game: a card of any game was printed in some languages. */}
              <SelectField
                label="Langue"
                allLabel="Toutes les langues"
                value={filters.language}
                onChange={(language) => onChange({ language })}
                options={FILTER_LANGUAGES.map(({ code, label }) => ({ value: code, label }))}
              />
              {showOwnership && (
                <SelectField
                  label="Possession"
                  allLabel="Toutes les cartes"
                  value={filters.ownership}
                  onChange={(ownership) => onChange({ ownership: parseOwnership(ownership) })}
                  options={[
                    { value: 'owned', label: 'Possédées' },
                    { value: 'missing', label: 'Manquantes' },
                  ]}
                />
              )}
            </>
          )}
        </div>
      </div>

      {/* The disabled fields say it in short; this is the full sentence, for assistive technologies. */}
      <p id={hintId} className="sr-only">
        {hasGame || grouped ? '' : 'Choisissez un jeu pour filtrer par extension et par rareté.'}
      </p>
      {canReset && (
        <div className="mt-2 flex justify-end text-sm">
          <button
            type="button"
            onClick={onReset}
            className="rounded-md font-medium text-accent underline-offset-4 hover:underline"
          >
            Réinitialiser les filtres
          </button>
        </div>
      )}

      {failedQueries.length > 0 && (
        <p role="alert" className="mt-2 text-sm text-danger">
          Impossible de charger certaines options de filtre.{' '}
          <button
            type="button"
            onClick={() => failedQueries.forEach((query) => void query.refetch())}
            className="rounded-md font-medium underline underline-offset-4"
          >
            Réessayer
          </button>
        </p>
      )}
    </form>
  )
}
