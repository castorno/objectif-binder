import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'

/**
 * Links a set to the one its cards come in the boosters of; null unlinks
 * it. What a set holds changes with it: its cards, its rarities, its rates.
 */
export function useSaveSetParent(setId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (parentId: string | null) =>
      apiRequest<{ parentCode: string | null }>(`/api/admin/sets/${encodeURIComponent(setId)}/parent`, {
        method: 'PUT',
        auth: true,
        body: { parentId },
      }),
    onSuccess: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: ['games'] }),
        queryClient.invalidateQueries({ queryKey: ['admin', 'pull-rates'] }),
        queryClient.invalidateQueries({ queryKey: ['cards'] }),
        queryClient.invalidateQueries({ queryKey: ['collection'] }),
      ]),
  })
}
