import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import { COLLECTION_QUERY_KEY, ownedCardsQuery } from '../../api/queries'
import type { CardCondition, OwnedCard } from '../../api/types'

type OwnedCardChange = { language: string; quantity: number; condition: CardCondition | null }

function ownedCardPath(cardId: string, language: string): string {
  return `/api/collection/cards/${encodeURIComponent(cardId)}/${encodeURIComponent(language)}`
}

/**
 * Adds the card in a language, or replaces its quantity and condition. The
 * request carries the whole new state: the API clears a condition left out.
 */
export function useSaveOwnedCard(cardId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ language, quantity, condition }: OwnedCardChange) =>
      apiRequest<OwnedCard>(ownedCardPath(cardId, language), {
        method: 'PUT',
        auth: true,
        body: { quantity, condition },
      }),
    onSuccess: (saved) => {
      // The API answered with the saved entry: show it without asking again.
      queryClient.setQueryData(ownedCardsQuery(cardId).queryKey, (current = []) =>
        [...current.filter((owned) => owned.language !== saved.language), saved].sort((a, b) =>
          a.language.localeCompare(b.language),
        ),
      )

      return queryClient.invalidateQueries({ queryKey: [...COLLECTION_QUERY_KEY, 'search'] })
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

      return queryClient.invalidateQueries({ queryKey: [...COLLECTION_QUERY_KEY, 'search'] })
    },
  })
}
