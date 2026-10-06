import { QueryCache, QueryClient } from '@tanstack/react-query'
import { ApiError } from './client'
import { SESSION_QUERY_KEY } from './queries'

const MAX_RETRIES = 2

/** Retrying a 4xx is pointless — the request itself is wrong (404, 422...). */
export function shouldRetry(failureCount: number, error: Error): boolean {
  return !(error instanceof ApiError && error.status < 500) && failureCount < MAX_RETRIES
}

export function createQueryClient(): QueryClient {
  const queryClient = new QueryClient({
    queryCache: new QueryCache({
      onError: (error) => {
        // Only authenticated requests can answer 401, and they do so after
        // failing to renew the token: the session is over. Say so in one
        // place, so every screen reflects it.
        if (error instanceof ApiError && error.status === 401) {
          queryClient.setQueryData(SESSION_QUERY_KEY, null)
        }
      },
    }),
    defaultOptions: {
      queries: {
        // Catalog data changes rarely (imports only): avoid refetching on every mount.
        staleTime: 60_000,
        retry: shouldRetry,
      },
    },
  })

  return queryClient
}
