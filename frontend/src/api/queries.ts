import { keepPreviousData, queryOptions } from '@tanstack/react-query'
import { ApiError, apiGet, apiRequest } from './client'
import type { CardDetail, CardSearchFilters, CardSet, CardSummary, Game, Paginated, Rarity, User } from './types'

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

export const cardSearchQuery = (filters: CardSearchFilters) => {
  const params = { ...filters, q: filters.q.trim(), limit: CARDS_PER_PAGE }

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
