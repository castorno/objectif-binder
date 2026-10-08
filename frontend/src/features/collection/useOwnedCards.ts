import { useMutation, useQueryClient, type QueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import { COLLECTION_QUERY_KEY, ownedCardsQuery } from '../../api/queries'
import type { CardCondition, Discovery, OwnedCard } from '../../api/types'

/** The saved entry, and what saving it started, if anything. */
export type SavedOwnedCard = OwnedCard & { discovery: Discovery | null }

type OwnedCardChange = { language: string; quantity: number; condition: CardCondition | null }

function ownedCardPath(cardId: string, language: string): string {
  return `/api/collection/cards/${encodeURIComponent(cardId)}/${encodeURIComponent(language)}`
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
 * Adds the card in a language, or replaces its quantity and condition. The
 * request carries the whole new state: the API clears a condition left out.
 */
export function useSaveOwnedCard(cardId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ language, quantity, condition }: OwnedCardChange) =>
      apiRequest<SavedOwnedCard>(ownedCardPath(cardId, language), {
        method: 'PUT',
        auth: true,
        body: { quantity, condition },
      }),
    onSuccess: (answer) => {
      // The discovery is news about this one change, not part of the entry.
      const saved: OwnedCard = {
        language: answer.language,
        quantity: answer.quantity,
        condition: answer.condition,
        acquiredAt: answer.acquiredAt,
      }

      // The API answered with the saved entry: show it without asking again.
      queryClient.setQueryData(ownedCardsQuery(cardId).queryKey, (current = []) =>
        [...current.filter((owned) => owned.language !== saved.language), saved].sort((a, b) =>
          a.language.localeCompare(b.language),
        ),
      )

      return refreshLists(queryClient)
    },
  })
}

export function useRemoveOwnedCard(cardId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (language: string) =>
      apiRequest<void>(ownedCardPath(cardId, language), { method: 'DELETE', auth: true }),
    onSuccess: (_, language) => {
      queryClient.setQueryData(ownedCardsQuery(cardId).queryKey, (current = []) =>
        current.filter((owned) => owned.language !== language),
      )

      return refreshLists(queryClient)
    },
  })
}
