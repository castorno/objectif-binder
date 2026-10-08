import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query'
import { setAccessToken } from '../../api/accessToken'
import { apiPost } from '../../api/client'
import { COLLECTION_QUERY_KEY, SESSION_QUERY_KEY, sessionQuery } from '../../api/queries'
import type { User } from '../../api/types'

export type Credentials = { email: string; password: string }

/**
 * Who is using the application. `user` is null for a visitor, and also while
 * `isPending`: at startup, until the API has said whether a session exists.
 */
export function useSession() {
  const session = useQuery(sessionQuery())

  return { user: session.data ?? null, isPending: session.isPending }
}

async function signIn(queryClient: QueryClient, credentials: Credentials): Promise<User | null> {
  const { token } = await apiPost<{ token: string }>('/api/auth/login', credentials)
  setAccessToken(token)
  // Whatever an earlier account left in the cache is not this one's.
  queryClient.removeQueries({ queryKey: COLLECTION_QUERY_KEY })

  // Ask the API who this is rather than trusting what was typed: the
  // session then holds the account as the server knows it.
  return queryClient.fetchQuery({ ...sessionQuery(), staleTime: 0 })
}

export function useLogin() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (credentials: Credentials) => signIn(queryClient, credentials),
  })
}

/** The account was created, but signing in with it right after failed. */
export class SignInAfterRegistrationError extends Error {
  constructor(cause: unknown) {
    super('The account was created but signing in failed.', { cause })
    this.name = 'SignInAfterRegistrationError'
  }
}

/**
 * Creates the account, then signs in with the same credentials: the API
 * only issues tokens through its login route, and nobody wants to type a
 * password twice in a row.
 */
export function useRegister() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (credentials: Credentials) => {
      await apiPost<User>('/api/auth/register', credentials)

      try {
        return await signIn(queryClient, credentials)
      } catch (error) {
        throw new SignInAfterRegistrationError(error)
      }
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
      queryClient.removeQueries({ queryKey: COLLECTION_QUERY_KEY })
    },
  })
}
