import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import { CARDS_QUERY_KEY, COLLECTION_QUERY_KEY, GAMES_QUERY_KEY, PULL_RATES_QUERY_KEY } from '../../api/queries'

/**
 * Names the rarity every card of a sub-set gets; null hands the rarities
 * back to the source. The rarities of the game, the cards and the rates
 * all change with it.
 *
 * `onSaved` is called from here and not passed to `mutate`: refreshing what
 * changed may mount the form anew, and a callback given to `mutate` is
 * dropped when its component goes away before the end.
 */
export function useSaveForcedRarity(setId: string, onSaved: (name: string | null) => void) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (name: string | null) =>
      apiRequest<{ forcedRarity: string | null }>(`/api/admin/sets/${encodeURIComponent(setId)}/forced-rarity`, {
        method: 'PUT',
        auth: true,
        body: { name },
      }),
    onSuccess: (_answer, name) => {
      onSaved(name)

      return Promise.all([
        queryClient.invalidateQueries({ queryKey: GAMES_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: PULL_RATES_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: CARDS_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: COLLECTION_QUERY_KEY }),
      ])
    },
  })
}
