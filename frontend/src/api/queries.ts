import { keepPreviousData, queryOptions } from '@tanstack/react-query'
import { ApiError, apiGet, apiRequest } from './client'
import type {
  CardDetail,
  CardSearchFilters,
  CardSet,
  CardSummary,
  CollectionCompletion,
  CollectionEntry,
  Game,
  OwnedCard,
  Paginated,
  Rarity,
  User,
} from './types'

export const CARDS_PER_PAGE = 20

export const gamesQuery = () =>
  queryOptions({
    queryKey: ['games'],
    queryFn: ({ signal }) => apiGet<Game[]>('/api/games', {}, signal),
  })

export const gameSetsQuery = (gameSlug: string) =>
  queryOptions({
    queryKey: ['games', gameSlug, 'sets'],
    queryFn: ({ signal }) => apiGet<CardSet[]>(`/api/games/${encodeURIComponent(gameSlug)}/sets`, {}, signal),
    enabled: gameSlug !== '',
  })

export const gameRaritiesQuery = (gameSlug: string) =>
  queryOptions({
    queryKey: ['games', gameSlug, 'rarities'],
    queryFn: ({ signal }) => apiGet<Rarity[]>(`/api/games/${encodeURIComponent(gameSlug)}/rarities`, {}, signal),
    enabled: gameSlug !== '',
  })

/**
 * What the API is asked for a search. `ownership` is left out: it is not a
 * parameter but a choice between routes, made by the caller.
 */
function searchParams({ q, game, set, rarity, page }: CardSearchFilters) {
  return { q: q.trim(), game, set, rarity, page, limit: CARDS_PER_PAGE }
}

export const cardSearchQuery = (filters: CardSearchFilters) => {
  const params = searchParams(filters)

  return queryOptions({
    queryKey: ['cards', 'search', params],
    queryFn: ({ signal }) => apiGet<Paginated<CardSummary>>('/api/cards', params, signal),
    // Keep the previous page on screen while the next one loads, instead of
    // flashing a skeleton on every keystroke or page change.
    placeholderData: keepPreviousData,
  })
}

export const cardDetailQuery = (id: string) =>
  queryOptions({
    queryKey: ['cards', 'detail', id],
    queryFn: ({ signal }) => apiGet<CardDetail>(`/api/cards/${encodeURIComponent(id)}`, {}, signal),
  })

/**
 * Everything under this key belongs to the signed-in user. It is emptied when
 * the session changes hands, so one account never sees another's cache.
 */
export const COLLECTION_QUERY_KEY = ['collection'] as const

export const collectionSearchQuery = (filters: CardSearchFilters) => {
  const params = searchParams(filters)

  return queryOptions({
    queryKey: [...COLLECTION_QUERY_KEY, 'search', params],
    queryFn: ({ signal }) =>
      apiRequest<Paginated<CollectionEntry>>('/api/collection', { auth: true, params, signal }),
    placeholderData: keepPreviousData,
  })
}

/**
 * The cards of a catalogue search the signed-in user does not own yet, in the
 * shape of `cardSearchQuery`.
 */
export const missingCardsQuery = (filters: CardSearchFilters) => {
  const params = searchParams(filters)

  return queryOptions({
    queryKey: [...COLLECTION_QUERY_KEY, 'missing', params],
    queryFn: ({ signal }) =>
      apiRequest<Paginated<CardSummary>>('/api/collection/missing', { auth: true, params, signal }),
    placeholderData: keepPreviousData,
  })
}

/**
 * How much of a catalogue search the signed-in user owns. Takes the filters
 * of `cardSearchQuery` and is asked alongside it: the catalogue itself is
 * public and the same for everyone.
 */
export const collectionCompletionQuery = (filters: CardSearchFilters) => {
  const params = searchParams(filters)

  return queryOptions({
    queryKey: [...COLLECTION_QUERY_KEY, 'completion', params],
    queryFn: ({ signal }) =>
      apiRequest<CollectionCompletion>('/api/collection/completion', { auth: true, params, signal }),
    placeholderData: keepPreviousData,
  })
}

/** The signed-in user's copies of one card, one entry per language. */
export const ownedCardsQuery = (cardId: string) =>
  queryOptions({
    queryKey: [...COLLECTION_QUERY_KEY, 'card', cardId],
    queryFn: async ({ signal }) => {
      const { data } = await apiRequest<{ data: OwnedCard[] }>(
        `/api/collection/cards/${encodeURIComponent(cardId)}`,
        { auth: true, signal },
      )

      return data
    },
  })

export const SESSION_QUERY_KEY = ['session'] as const

/**
 * Who is using the application: the signed-in user, or null for a visitor.
 * The first call is what restores a session after a page reload: no access
 * token is in memory yet, so the client renews one from the refresh cookie.
 */
export const sessionQuery = () =>
  queryOptions({
    queryKey: SESSION_QUERY_KEY,
    queryFn: async ({ signal }): Promise<User | null> => {
      try {
        return await apiRequest<User>('/api/me', { auth: true, signal })
      } catch (error) {
        // Not being signed in is an answer, not a failure.
        if (error instanceof ApiError && error.status === 401) return null
        throw error
      }
    },
    // Only signing in, signing out or losing the session changes it, and each
    // of those updates this entry directly.
    staleTime: Infinity,
  })
