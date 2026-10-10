import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import { setPullRatesQuery } from '../../api/queries'
import type { PullRate, SetPullRates } from '../../api/types'

export type PullRatesChange = {
  /** By rarity id; a rarity left out loses its rate. */
  rates: Record<string, PullRate>
  source: string | null
}

export function useSavePullRates(setId: string, onSaved: () => void) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (change: PullRatesChange) =>
      apiRequest<SetPullRates>(`/api/admin/sets/${encodeURIComponent(setId)}/pull-rates`, {
        method: 'PUT',
        auth: true,
        body: change,
      }),
    onSuccess: (saved) => {
      // Called from here and not passed to `mutate`: showing the saved rates
      // mounts the form anew, and a callback given to `mutate` is dropped
      // when its component goes away before the end.
      onSaved()
      // The API answered with the saved rates: show them without asking again.
      queryClient.setQueryData(setPullRatesQuery(setId).queryKey, saved)
      // The odds shown on the pages of the cards of this set changed with them.
      return queryClient.invalidateQueries({ queryKey: ['cards'] })
    },
  })
}
