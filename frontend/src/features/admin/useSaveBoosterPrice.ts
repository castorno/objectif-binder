import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'

/**
 * Enters what a booster of a set costs, in euro cents; null for a price
 * that is not known. `onSaved` is called from here and not passed to
 * `mutate`: see useSaveForcedRarity.
 */
export function useSaveBoosterPrice(setId: string, onSaved: (priceCents: number | null) => void) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (priceCents: number | null) =>
      apiRequest<{ boosterPriceCents: number | null }>(`/api/admin/sets/${encodeURIComponent(setId)}/booster-price`, {
        method: 'PUT',
        auth: true,
        body: { priceCents },
      }),
    onSuccess: (_answer, priceCents) => {
      onSaved(priceCents)

      return Promise.all([
        // What is left to enter for the sets of the game.
        queryClient.invalidateQueries({ queryKey: ['admin', 'set-progress'] }),
        queryClient.invalidateQueries({ queryKey: ['admin', 'pull-rates'] }),
        queryClient.invalidateQueries({ queryKey: ['collection', 'boosters'] }),
      ])
    },
  })
}
