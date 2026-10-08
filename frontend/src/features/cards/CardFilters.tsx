import { useQuery } from '@tanstack/react-query'
import { useId } from 'react'
import { gameRaritiesQuery, gameSetsQuery, gamesQuery } from '../../api/queries'
import type { CardSearchFilters } from '../../api/types'
import { SelectField } from '../../components/SelectField'
import { hasActiveFilters, parseOwnership } from './useCardSearchParams'

type CardFiltersProps = {
  filters: CardSearchFilters
  /** Text currently typed in the search field, possibly not applied to `filters.q` yet. */
  queryDraft: string
  onQueryDraftChange: (value: string) => void
  onChange: (patch: Partial<Omit<CardSearchFilters, 'page'>>) => void
  onReset: () => void
  /** Offer to narrow the search to owned or missing cards: for a signed-in user only. */
  showOwnership?: boolean
}

export function CardFilters({
  filters,
  queryDraft,
  onQueryDraftChange,
  onChange,
  onReset,
  showOwnership = false,
}: CardFiltersProps) {
  const searchId = useId()
  const hintId = useId()

  const games = useQuery(gamesQuery())
  const sets = useQuery(gameSetsQuery(filters.game))
  const rarities = useQuery(gameRaritiesQuery(filters.game))

  const failedQueries = [games, sets, rarities].filter((query) => query.isError)

  const hasGame = filters.game !== ''
  // A draft not yet sent to the URL counts too: it is about to become a filter.
  const canReset = hasActiveFilters(filters) || queryDraft !== ''

  return (
    <form
      role="search"
      aria-label="Rechercher des cartes"
      onSubmit={(event) => {
        event.preventDefault()
        // Enter applies the search right away instead of waiting for the debounce.
        onChange({ q: queryDraft })
      }}
      className="rounded-xl border border-line bg-surface p-4"
    >
      <div
        className={`grid gap-4 sm:grid-cols-2 ${showOwnership ? 'lg:grid-cols-[2fr_1fr_1fr_1fr_1fr]' : 'lg:grid-cols-[2fr_1fr_1fr_1fr]'}`}
      >
        <div className="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
          <label htmlFor={searchId} className="text-sm font-medium">
            Nom de la carte
          </label>
          <input
            id={searchId}
            type="search"
            value={queryDraft}
            maxLength={100}
            placeholder="Ex. : Sentinelle"
            autoComplete="off"
            onChange={(event) => onQueryDraftChange(event.target.value)}
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
        <SelectField
          label="Extension"
          allLabel="Toutes les extensions"
          value={filters.set}
          disabled={!hasGame}
          describedBy={hasGame ? undefined : hintId}
          onChange={(set) => onChange({ set })}
          options={(sets.data ?? []).map((set) => ({ value: set.code, label: `${set.name} (${set.code})` }))}
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
      </div>

      <div className="mt-3 flex min-h-6 flex-wrap items-center justify-between gap-2 text-sm">
        <p id={hintId} className="text-muted">
          {hasGame ? '' : 'Choisissez un jeu pour filtrer par extension et par rareté.'}
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
