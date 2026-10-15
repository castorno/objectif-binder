import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import { BOOSTERS_QUERY_KEY, PULL_RATES_QUERY_KEY } from '../../api/queries'

/**
 * Sends a picture for a set, or takes it away when given null. `onSaved`
 * is called from here and not passed to `mutate`: see useSaveForcedRarity.
 */
export function useSaveSetImage(setId: string, onSaved: (sent: boolean) => void) {
  const queryClient = useQueryClient()
  const path = `/api/admin/sets/${encodeURIComponent(setId)}/image`

  return useMutation({
    mutationFn: (file: File | null) => {
      if (file === null) return apiRequest<{ imageUrl: string | null }>(path, { method: 'DELETE', auth: true })

      const form = new FormData()
      form.append('image', file)

      return apiRequest<{ imageUrl: string | null }>(path, { method: 'POST', auth: true, body: form })
    },
    onSuccess: (_answer, file) => {
      onSaved(file !== null)

      return Promise.all([
        queryClient.invalidateQueries({ queryKey: PULL_RATES_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: BOOSTERS_QUERY_KEY }),
      ])
    },
  })
}
