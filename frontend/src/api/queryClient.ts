import { QueryClient } from '@tanstack/react-query'
import { ApiError } from './client'

const MAX_RETRIES = 2

/** Retrying a 4xx is pointless — the request itself is wrong (404, 422...). */
export function shouldRetry(failureCount: number, error: Error): boolean {
  return !(error instanceof ApiError && error.status < 500) && failureCount < MAX_RETRIES
}

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        // Catalog data changes rarely (imports only): avoid refetching on every mount.
        staleTime: 60_000,
        retry: shouldRetry,
      },
    },
  })
}
