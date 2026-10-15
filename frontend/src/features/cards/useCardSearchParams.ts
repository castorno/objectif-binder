import { useCallback, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router'
import { WITHOUT_IDENTITY, type CardSearchFilters, type CardSort, type CatalogueView, type Ownership } from '../../api/types'
import { DEFAULT_PAGE_SIZE, PAGE_SIZES, pageForSize } from '../../lib/pagination'
import { useDebouncedEffect } from '../../lib/useDebouncedEffect'

const SEARCH_DEBOUNCE_MS = 300

function parsePage(raw: string | null): number {
  const page = Number(raw)

  return Number.isInteger(page) && page >= 1 ? page : 1
}

/** Anything else than an offered size, e.g. in a hand-edited URL, means the default one. */
export function parseLimit(raw: string | null): number {
  const limit = Number(raw)

  return PAGE_SIZES.some((size) => size === limit) ? limit : DEFAULT_PAGE_SIZE
}

/** Where "Ma collection" leads: the catalogue narrowed to the owned cards. */
export const OWNED_CARDS_PATH = '/?ownership=owned'

/** Anything else than a known value, e.g. in a hand-edited URL, means every card. */
export function parseOwnership(raw: string | null): Ownership {
  return raw === 'owned' || raw === 'missing' ? raw : ''
}

const IDENTITY_ID = /^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i

/** The API refuses anything else: a hand-edited value is dropped rather than sent. */
export function parseIdentity(raw: string | null): string {
  return raw !== null && (raw === WITHOUT_IDENTITY || IDENTITY_ID.test(raw)) ? raw : ''
}

/** The API refuses a longer name: a hand-edited value is dropped rather than sent. */
export function parseGroup(raw: string | null): string {
  const group = (raw ?? '').trim()

  return group.length <= 100 ? group : ''
}

/** The API refuses anything else than a two-letter code: a hand-edited value is dropped rather than sent. */
export function parseLanguage(raw: string | null): string {
  return raw !== null && /^[a-z]{2}$/.test(raw) ? raw : ''
}

/** Anything else than a known value means the order of the catalogue. */
export function parseSort(raw: string | null): CardSort {
  return raw === 'newest' ? raw : ''
}

export function parseView(raw: string | null): CatalogueView {
  return raw === 'identities' ? raw : ''
}

export function filtersFromSearchParams(params: URLSearchParams): CardSearchFilters {
  return {
    q: params.get('q') ?? '',
    game: params.get('game') ?? '',
    set: params.get('set') ?? '',
    rarity: params.get('rarity') ?? '',
    language: parseLanguage(params.get('language')),
    ownership: parseOwnership(params.get('ownership')),
    identity: parseIdentity(params.get('identity')),
    group: parseGroup(params.get('group')),
    sort: parseSort(params.get('sort')),
    view: parseView(params.get('view')),
    page: parsePage(params.get('page')),
    limit: parseLimit(params.get('limit')),
  }
}

/** A search that narrows nothing, in the default order and page size. */
export const noFilters: CardSearchFilters = {
  q: '',
  game: '',
  set: '',
  rarity: '',
  language: '',
  ownership: '',
  identity: '',
  group: '',
  sort: '',
  view: '',
  page: 1,
  limit: DEFAULT_PAGE_SIZE,
}

export function filtersToSearchParams(filters: CardSearchFilters): URLSearchParams {
  const params = new URLSearchParams()
  if (filters.q !== '') params.set('q', filters.q)
  if (filters.game !== '') params.set('game', filters.game)
  if (filters.set !== '') params.set('set', filters.set)
  if (filters.rarity !== '') params.set('rarity', filters.rarity)
  if (filters.language !== '') params.set('language', filters.language)
  if (filters.ownership !== '') params.set('ownership', filters.ownership)
  if (filters.identity !== '') params.set('identity', filters.identity)
  if (filters.group !== '') params.set('group', filters.group)
  if (filters.sort !== '') params.set('sort', filters.sort)
  if (filters.view !== '') params.set('view', filters.view)
  if (filters.page > 1) params.set('page', String(filters.page))
  if (filters.limit !== DEFAULT_PAGE_SIZE) params.set('limit', String(filters.limit))

  return params
}

/** The catalogue narrowed to the cards of a set. */
export function setCardsPath(gameSlug: string, setCode: string): string {
  return `/?${new URLSearchParams({ game: gameSlug, set: setCode })}`
}

/** The cards of a set the signed-in user does not own yet. */
export function missingSetCardsPath(gameSlug: string, setCode: string): string {
  return `/?${new URLSearchParams({ game: gameSlug, set: setCode, ownership: 'missing' })}`
}

/** Whether the search is narrowed by something else than the ownership. */
export function hasCatalogueFilters(filters: CardSearchFilters): boolean {
  return filters.q !== '' || filters.game !== '' || filters.set !== '' || filters.rarity !== '' || filters.language !== ''
}

/** Whether anything narrows the search; the page number is not a filter. */
export function hasActiveFilters(filters: CardSearchFilters): boolean {
  return (
    hasCatalogueFilters(filters) ||
    filters.ownership !== '' ||
    filters.identity !== '' ||
    // A group only narrows the grouped catalogue: elsewhere it is not in effect.
    (filters.view === 'identities' && filters.group !== '')
  )
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
    (patch: Partial<Omit<CardSearchFilters, 'page' | 'view' | 'limit'>>) => {
      setSearchParams(
        (current) => {
          // Any filter change invalidates the current page number.
          const next = { ...filtersFromSearchParams(current), ...patch, page: 1 }
          // Sets, rarities, identities and their groups belong to a game: they mean nothing once it changes.
          if (patch.game !== undefined) {
            next.set = ''
            next.rarity = ''
            next.identity = ''
            next.group = ''
          }

          return filtersToSearchParams(next)
        },
        // Refining a search should not pile up one history entry per keystroke.
        { replace: true },
      )
    },
    [setSearchParams],
  )

  /**
   * Changes how many entries a page holds, staying on the page that holds
   * the first entry on screen. A history entry of its own: the back button
   * brings the previous size back.
   */
  const setPageSize = useCallback(
    (limit: number) => {
      setSearchParams((current) => {
        const filters = filtersFromSearchParams(current)

        return filtersToSearchParams({ ...filters, limit, page: pageForSize(filters.page, filters.limit, limit) })
      })
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
    // Neither the view nor the page size is a filter: resetting keeps them.
    setSearchParams((current) => {
      const { view, limit } = filtersFromSearchParams(current)

      return filtersToSearchParams({ ...noFilters, view, limit })
    })
  }, [setSearchParams])

  return { filters, queryDraft, setQueryDraft, updateFilters, resetFilters, setPageSize }
}
