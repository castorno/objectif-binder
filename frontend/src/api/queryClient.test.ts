import { describe, expect, it } from 'vitest'
import { ApiError } from './client'
import { SESSION_QUERY_KEY } from './queries'
import { createQueryClient, shouldRetry } from './queryClient'

describe('shouldRetry', () => {
  it.each([400, 404, 422])('does not retry a %i: the request itself is wrong', (status) => {
    expect(shouldRetry(0, new ApiError(status, 'Client error'))).toBe(false)
  })

  it('retries a server error twice, then gives up', () => {
    const error = new ApiError(503, 'Service unavailable')

    expect(shouldRetry(0, error)).toBe(true)
    expect(shouldRetry(1, error)).toBe(true)
    expect(shouldRetry(2, error)).toBe(false)
  })

  it('retries a network failure, which never reached the API', () => {
    const error = new TypeError('Failed to fetch')

    expect(shouldRetry(0, error)).toBe(true)
    expect(shouldRetry(2, error)).toBe(false)
  })
})

describe('createQueryClient', () => {
  it('applies the retry rule to every query', () => {
    expect(createQueryClient().getDefaultOptions().queries?.retry).toBe(shouldRetry)
  })

  it('ends the session when a request answers 401 despite the token renewal', async () => {
    const queryClient = createQueryClient()
    queryClient.setQueryData(SESSION_QUERY_KEY, { id: 'user-1', email: 'camille@example.com' })

    await queryClient
      .fetchQuery({ queryKey: ['collection'], queryFn: () => Promise.reject(new ApiError(401, 'Expired token.')) })
      .catch(() => {})

    expect(queryClient.getQueryData(SESSION_QUERY_KEY)).toBeNull()
  })

  it('keeps the session on any other failure', async () => {
    const queryClient = createQueryClient()
    const user = { id: 'user-1', email: 'camille@example.com' }
    queryClient.setQueryData(SESSION_QUERY_KEY, user)

    await queryClient
      .fetchQuery({ queryKey: ['collection'], retry: false, queryFn: () => Promise.reject(new ApiError(500, 'Oops')) })
      .catch(() => {})

    expect(queryClient.getQueryData(SESSION_QUERY_KEY)).toEqual(user)
  })
})
