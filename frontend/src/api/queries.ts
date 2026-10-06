import { keepPreviousData, queryOptions } from '@tanstack/react-query'
import { apiGet } from './client'
import type { CardDetail, CardSearchFilters, CardSet, CardSummary, Game, Paginated, Rarity } from './types'

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
