import { useQuery } from '@tanstack/react-query'
import { useId } from 'react'
import { gameIdentityGroupsQuery, gameRaritiesQuery, gameSetsQuery, gamesQuery } from '../../api/queries'
import type { CardSearchFilters } from '../../api/types'
import { ComboboxField } from '../../components/ComboboxField'
import { SelectField } from '../../components/SelectField'
import { formatShortMonth } from '../../lib/dates'
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

  const games = useQuery(gamesQuery())
  const sets = useQuery({ ...gameSetsQuery(filters.game), enabled: filters.game !== '' && !grouped })
  const rarities = useQuery({ ...gameRaritiesQuery(filters.game), enabled: filters.game !== '' && !grouped })

  const groups = useQuery({ ...gameIdentityGroupsQuery(filters.game), enabled: filters.game !== '' && grouped })

  const failedQueries = [games, sets, rarities, groups].filter((query) => query.isError)

  // Offered only for a game that sorts its identities into groups, under the name the game gives them.
  const groupLabel = games.data?.find((game) => game.slug === filters.game)?.identityGroupLabel ?? 'Groupe'
  const showsGroups = grouped && (filters.group !== '' || (groups.data ?? []).length > 0)

  const hasGame = filters.game !== ''
  // A draft not yet sent to the URL counts too: it is about to become a filter.
  const canReset = hasActiveFilters(filters) || queryDraft !== ''

  return (
    <form
      role="search"
      aria-label="Rechercher des cartes"
      onSubmit={(event) => event.preventDefault()}
      className="rounded-xl border border-line bg-surface p-4"
    >
      <div
        className={`grid gap-4 sm:grid-cols-2 ${
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
            className="h-10 rounded-lg border border-line bg-surface px-3 text-sm placeholder:text-muted"
          />
        </div>

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
              allLabel="Toutes les extensions"
              emptyMessage="Aucune extension ne correspond."
              value={filters.set}
              disabled={!hasGame}
              describedBy={hasGame ? undefined : hintId}
              onChange={(set) => onChange({ set })}
              options={(sets.data ?? []).map((set) => ({
                value: set.code,
                label: set.name,
                detail: [set.releaseDate === null ? '' : formatShortMonth(set.releaseDate), set.code]
                  .filter((part) => part !== '')
                  .join(' · '),
                // Also found by its code.
                keywords: set.code,
              }))}
            />
            <SelectField
              label="Rareté"
              allLabel="Toutes les raretés"
              value={filters.rarity}
              disabled={!hasGame}
              describedBy={hasGame ? undefined : hintId}
              onChange={(rarity) => onChange({ rarity })}
              options={(rarities.data ?? []).map((rarity) => ({ value: rarity.name, label: rarity.name }))}
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

      <div className="mt-3 flex min-h-6 flex-wrap items-center justify-between gap-2 text-sm">
        <p id={hintId} className="text-muted">
          {hasGame || grouped ? '' : 'Choisissez un jeu pour filtrer par extension et par rareté.'}
        </p>
        {canReset && (
          <button
            type="button"
            onClick={onReset}
            className="rounded-md font-medium text-accent underline-offset-4 hover:underline"
          >
            Réinitialiser les filtres
          </button>
        )}
      </div>

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
