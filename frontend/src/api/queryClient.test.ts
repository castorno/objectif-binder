import { describe, expect, it } from 'vitest'
import { ApiError } from './client'
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
})
