import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'

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
        queryClient.invalidateQueries({ queryKey: ['games'] }),
        queryClient.invalidateQueries({ queryKey: ['admin', 'pull-rates'] }),
        queryClient.invalidateQueries({ queryKey: ['cards'] }),
        queryClient.invalidateQueries({ queryKey: ['collection'] }),
      ])
    },
  })
}
