import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import { CARDS_QUERY_KEY, COLLECTION_QUERY_KEY, GAMES_QUERY_KEY, PULL_RATES_QUERY_KEY, SET_PROGRESS_QUERY_KEY } from '../../api/queries'

/**
 * Links a set to the one its cards come in the boosters of; null unlinks
 * it. What a set holds changes with it: its cards, its rarities, its rates.
 */
export function useSaveSetParent(setId: string, onSaved: (parentId: string | null) => void) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (parentId: string | null) =>
      apiRequest<{ parentCode: string | null }>(`/api/admin/sets/${encodeURIComponent(setId)}/parent`, {
        method: 'PUT',
        auth: true,
        body: { parentId },
      }),
    // Called from here and not passed to `mutate`: see useSaveForcedRarity.
    onSuccess: (_answer, parentId) => {
      onSaved(parentId)

      return Promise.all([
        // What is left to enter for the sets of the game.
        queryClient.invalidateQueries({ queryKey: SET_PROGRESS_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: GAMES_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: PULL_RATES_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: CARDS_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: COLLECTION_QUERY_KEY }),
      ])
    },
  })
}
