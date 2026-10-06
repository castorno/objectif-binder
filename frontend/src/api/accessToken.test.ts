import { http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { server } from '../test/server'
import { getAccessToken, refreshAccessToken, setAccessToken } from './accessToken'

function refreshRoute(respond: () => Response) {
  let calls = 0
  server.use(
    http.post('*/api/auth/refresh', () => {
      calls++

      return respond()
    }),
  )

  return { calls: () => calls }
}

afterEach(() => {
  setAccessToken(null)
  vi.unstubAllGlobals()
})

describe('refreshAccessToken', () => {
  it('stores and returns the new access token', async () => {
    refreshRoute(() => HttpResponse.json({ token: 'fresh-token' }))

    await expect(refreshAccessToken()).resolves.toBe('fresh-token')
    expect(getAccessToken()).toBe('fresh-token')
  })

  it('resolves to null and forgets the token when there is no session to renew', async () => {
    setAccessToken('old-token')
    refreshRoute(() => HttpResponse.json({ error: 'Invalid or expired refresh token.' }, { status: 401 }))

    await expect(refreshAccessToken()).resolves.toBeNull()
    expect(getAccessToken()).toBeNull()
  })

  it('rejects, without touching the token, when the API cannot answer', async () => {
    // A server error says nothing about the session: do not sign the user out for it.
    setAccessToken('current-token')
    refreshRoute(() => HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 }))

    await expect(refreshAccessToken()).rejects.toMatchObject({ status: 500 })
    expect(getAccessToken()).toBe('current-token')
  })

  it('rejects when rate limited, again without signing the user out', async () => {
    setAccessToken('current-token')
    refreshRoute(() => HttpResponse.json({ error: 'Too many requests.' }, { status: 429 }))

    await expect(refreshAccessToken()).rejects.toMatchObject({ status: 429 })
    expect(getAccessToken()).toBe('current-token')
  })

  it('shares one request between simultaneous calls', async () => {
    const route = refreshRoute(() => HttpResponse.json({ token: 'fresh-token' }))

    const tokens = await Promise.all([refreshAccessToken(), refreshAccessToken(), refreshAccessToken()])

    expect(tokens).toEqual(['fresh-token', 'fresh-token', 'fresh-token'])
    expect(route.calls()).toBe(1)
  })

  it('makes a new request once the previous one has settled', async () => {
    const route = refreshRoute(() => HttpResponse.json({ token: 'fresh-token' }))

    await refreshAccessToken()
    await refreshAccessToken()

    expect(route.calls()).toBe(2)
  })

  it('takes a lock shared with the other tabs where the browser offers one', async () => {
    refreshRoute(() => HttpResponse.json({ token: 'fresh-token' }))
    // jsdom has no Web Locks API: stand in for a browser that does.
    const request = vi.fn((_name: string, task: () => Promise<unknown>) => task())
    vi.stubGlobal('navigator', { locks: { request } })

    await expect(refreshAccessToken()).resolves.toBe('fresh-token')

    expect(request).toHaveBeenCalledExactlyOnceWith('objectif-binder:token-refresh', expect.any(Function))
  })
})
