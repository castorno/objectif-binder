import { useMutation, useQueryClient, type QueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import { COLLECTION_QUERY_KEY, ownedCardsQuery } from '../../api/queries'
import type { CardCondition, CardFinish, Discovery, OwnedCard } from '../../api/types'
import { FINISHES } from './finishes'

/** The saved entry, and what saving it started, if anything. */
export type SavedOwnedCard = OwnedCard & { discovery: Discovery | null }

/** What identifies an entry of the collection, for a given card. */
export type OwnedCardKey = { language: string; finish: CardFinish }

type OwnedCardChange = OwnedCardKey & { quantity: number; condition: CardCondition | null }

function ownedCardPath(cardId: string, { language, finish }: OwnedCardKey): string {
  return `/api/collection/cards/${encodeURIComponent(cardId)}/${encodeURIComponent(language)}/${encodeURIComponent(finish)}`
}

function isSameEntry(a: OwnedCardKey, b: OwnedCardKey): boolean {
  return a.language === b.language && a.finish === b.finish
}

/** By language, then by finish in the usual order. */
function compareEntries(a: OwnedCardKey, b: OwnedCardKey): number {
  const rank = (finish: CardFinish) => FINISHES.findIndex((candidate) => candidate.value === finish)

  return a.language.localeCompare(b.language) || rank(a.finish) - rank(b.finish)
}

/** The owned and missing lists and the completion rates, per search or per identity, no longer match what is owned. */
function refreshLists(queryClient: QueryClient) {
  return Promise.all([
    queryClient.invalidateQueries({ queryKey: [...COLLECTION_QUERY_KEY, 'search'] }),
    queryClient.invalidateQueries({ queryKey: [...COLLECTION_QUERY_KEY, 'missing'] }),
    queryClient.invalidateQueries({ queryKey: [...COLLECTION_QUERY_KEY, 'identities'] }),
    queryClient.invalidateQueries({ queryKey: [...COLLECTION_QUERY_KEY, 'completion'] }),
  ])
}

/**
 * Adds the card in a language and a finish, or replaces its quantity and condition. The
 * request carries the whole new state: the API clears a condition left out.
 */
export function useSaveOwnedCard(cardId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ language, finish, quantity, condition }: OwnedCardChange) =>
      apiRequest<SavedOwnedCard>(ownedCardPath(cardId, { language, finish }), {
        method: 'PUT',
        auth: true,
        body: { quantity, condition },
      }),
    onSuccess: (answer) => {
      // The discovery is news about this one change, not part of the entry.
      const saved: OwnedCard = {
        language: answer.language,
        finish: answer.finish,
        quantity: answer.quantity,
        condition: answer.condition,
        acquiredAt: answer.acquiredAt,
      }

      // The API answered with the saved entry: show it without asking again.
      queryClient.setQueryData(ownedCardsQuery(cardId).queryKey, (current = []) =>
        [...current.filter((owned) => !isSameEntry(owned, saved)), saved].sort(compareEntries),
      )

      return refreshLists(queryClient)
    },
  })
}

export function useRemoveOwnedCard(cardId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (entry: OwnedCardKey) =>
      apiRequest<void>(ownedCardPath(cardId, entry), { method: 'DELETE', auth: true }),
    onSuccess: (_, entry) => {
      queryClient.setQueryData(ownedCardsQuery(cardId).queryKey, (current = []) =>
        current.filter((owned) => !isSameEntry(owned, entry)),
      )

      return refreshLists(queryClient)
    },
  })
}
