import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { setAccessToken } from '../../api/accessToken'
import { apiPost } from '../../api/client'
import { SESSION_QUERY_KEY, sessionQuery } from '../../api/queries'

export type Credentials = { email: string; password: string }

/**
 * Who is using the application. `user` is null for a visitor, and also while
 * `isPending`: at startup, until the API has said whether a session exists.
 */
export function useSession() {
  const session = useQuery(sessionQuery())

  return { user: session.data ?? null, isPending: session.isPending }
}

export function useLogin() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (credentials: Credentials) => {
      const { token } = await apiPost<{ token: string }>('/api/auth/login', credentials)
      setAccessToken(token)

      // Ask the API who this is rather than trusting what was typed: the
      // session then holds the account as the server knows it.
      return queryClient.fetchQuery({ ...sessionQuery(), staleTime: 0 })
    },
  })
}

export function useLogout() {
  const queryClient = useQueryClient()

  return useMutation({
    // Sent without the access token: the refresh cookie is what identifies
    // the session to end, and an expired token would get the request refused.
    mutationFn: () => apiPost<void>('/api/auth/logout'),
    // Only once the API has confirmed. Looking signed out while the session
    // cookie is still valid would be worse than a visible failure.
    onSuccess: () => {
      setAccessToken(null)
      queryClient.setQueryData(SESSION_QUERY_KEY, null)
    },
  })
}
