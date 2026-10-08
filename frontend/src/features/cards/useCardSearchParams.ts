import { useCallback, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router'
import type { CardSearchFilters, Ownership } from '../../api/types'
import { useDebouncedEffect } from '../../lib/useDebouncedEffect'

const SEARCH_DEBOUNCE_MS = 300

function parsePage(raw: string | null): number {
  const page = Number(raw)

  return Number.isInteger(page) && page >= 1 ? page : 1
}

/** Where "Ma collection" leads: the catalogue narrowed to the owned cards. */
export const OWNED_CARDS_PATH = '/?ownership=owned'

/** Anything else than a known value, e.g. in a hand-edited URL, means every card. */
export function parseOwnership(raw: string | null): Ownership {
  return raw === 'owned' || raw === 'missing' ? raw : ''
}

export function filtersFromSearchParams(params: URLSearchParams): CardSearchFilters {
  return {
    q: params.get('q') ?? '',
    game: params.get('game') ?? '',
    set: params.get('set') ?? '',
    rarity: params.get('rarity') ?? '',
    ownership: parseOwnership(params.get('ownership')),
    page: parsePage(params.get('page')),
  }
}

export function filtersToSearchParams(filters: CardSearchFilters): URLSearchParams {
  const params = new URLSearchParams()
  if (filters.q !== '') params.set('q', filters.q)
  if (filters.game !== '') params.set('game', filters.game)
  if (filters.set !== '') params.set('set', filters.set)
  if (filters.rarity !== '') params.set('rarity', filters.rarity)
  if (filters.ownership !== '') params.set('ownership', filters.ownership)
  if (filters.page > 1) params.set('page', String(filters.page))

  return params
}

/** Whether the search is narrowed by something else than the ownership. */
export function hasCatalogueFilters(filters: CardSearchFilters): boolean {
  return filters.q !== '' || filters.game !== '' || filters.set !== '' || filters.rarity !== ''
}

/** Whether anything narrows the search; the page number is not a filter. */
export function hasActiveFilters(filters: CardSearchFilters): boolean {
  return hasCatalogueFilters(filters) || filters.ownership !== ''
}

/**
 * The URL is the single source of truth for the search state: a search can be
 * bookmarked or shared, and the browser back button restores it.
 *
 * The one exception is `queryDraft`, the text being typed: it lives here so
 * typing stays instant, and only reaches the URL (and so the API) once the
 * user pauses.
 */
export function useCardSearchParams() {
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(() => filtersFromSearchParams(searchParams), [searchParams])

  const updateFilters = useCallback(
    (patch: Partial<Omit<CardSearchFilters, 'page'>>) => {
      setSearchParams(
        (current) => {
          // Any filter change invalidates the current page number.
          const next = { ...filtersFromSearchParams(current), ...patch, page: 1 }
          // Sets and rarities belong to a game: they mean nothing once it changes.
          if (patch.game !== undefined) {
            next.set = ''
            next.rarity = ''
          }

          return filtersToSearchParams(next)
        },
        // Refining a search should not pile up one history entry per keystroke.
        { replace: true },
      )
    },
    [setSearchParams],
  )

  const [queryDraft, setQueryDraft] = useState(filters.q)
  const [syncedQuery, setSyncedQuery] = useState(filters.q)
  if (filters.q !== syncedQuery) {
    // The URL changed from outside the input (back button, reset): follow it.
    setSyncedQuery(filters.q)
    setQueryDraft(filters.q)
  }
  useDebouncedEffect(queryDraft, SEARCH_DEBOUNCE_MS, (value) => {
    if (value !== filters.q) updateFilters({ q: value })
  })

  const resetFilters = useCallback(() => {
    // Clear the draft too: if it has not reached the URL yet, the pending
    // debounce would otherwise re-apply it right after the reset.
    setQueryDraft('')
    setSearchParams(new URLSearchParams())
  }, [setSearchParams])

  return { filters, queryDraft, setQueryDraft, updateFilters, resetFilters }
}
