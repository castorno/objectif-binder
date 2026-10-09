import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import { setPullRatesQuery } from '../../api/queries'
import type { PullRate, SetPullRates } from '../../api/types'

export type PullRatesChange = {
  /** By rarity id; a rarity left out loses its rate. */
  rates: Record<string, PullRate>
  source: string | null
}

export function useSavePullRates(setId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (change: PullRatesChange) =>
      apiRequest<SetPullRates>(`/api/admin/sets/${encodeURIComponent(setId)}/pull-rates`, {
        method: 'PUT',
        auth: true,
        body: change,
      }),
    onSuccess: (saved) => {
      // The API answered with the saved rates: show them without asking again.
      queryClient.setQueryData(setPullRatesQuery(setId).queryKey, saved)
      // The odds shown on the pages of the cards of this set changed with them.
      return queryClient.invalidateQueries({ queryKey: ['cards'] })
    },
  })
}
