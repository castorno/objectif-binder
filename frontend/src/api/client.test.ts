import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiGet } from './client'

function stubFetch(response: Response) {
  const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(response)
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

function requestedUrl(fetchMock: ReturnType<typeof stubFetch>): URL {
  return fetchMock.mock.calls[0][0] as URL
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('apiGet', () => {
  it('returns the decoded JSON body', async () => {
    stubFetch(Response.json([{ id: '1', name: 'Demo', slug: 'demo' }]))

    await expect(apiGet('/api/games')).resolves.toEqual([{ id: '1', name: 'Demo', slug: 'demo' }])
  })

  it('requests the path on the configured API with the query parameters', async () => {
    const fetchMock = stubFetch(Response.json({}))

    await apiGet('/api/cards', { q: 'feu & glace', page: 2 })

    const url = requestedUrl(fetchMock)
    expect(url.origin).toBe(new URL(import.meta.env.VITE_API_URL).origin)
    expect(url.pathname).toBe('/api/cards')
    expect(url.searchParams.get('q')).toBe('feu & glace')
    expect(url.searchParams.get('page')).toBe('2')
  })

  it('leaves out empty and undefined parameters', async () => {
    const fetchMock = stubFetch(Response.json({}))

    await apiGet('/api/cards', { q: '', game: undefined, set: 'AAA' })

    expect([...requestedUrl(fetchMock).searchParams.keys()]).toEqual(['set'])
  })

  it('asks for JSON and forwards the abort signal', async () => {
    const fetchMock = stubFetch(Response.json({}))
    const { signal } = new AbortController()

    await apiGet('/api/games', {}, signal)

    expect(fetchMock.mock.calls[0][1]).toEqual({ signal, headers: { Accept: 'application/json' } })
  })

  it('throws an ApiError carrying the status and the message sent by the API', async () => {
    stubFetch(Response.json({ error: 'Card not found.' }, { status: 404 }))

    const error = await apiGet('/api/cards/unknown').catch((reason: unknown) => reason)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ name: 'ApiError', status: 404, message: 'Card not found.' })
  })

  it('falls back to the HTTP status when the error body is not JSON', async () => {
    stubFetch(new Response('<html>Bad Gateway</html>', { status: 502 }))

    await expect(apiGet('/api/cards')).rejects.toMatchObject({ status: 502, message: 'HTTP 502' })
  })

  it('falls back to the HTTP status when the error body has no usable message', async () => {
    stubFetch(Response.json({ error: { code: 42 } }, { status: 500 }))

    await expect(apiGet('/api/cards')).rejects.toMatchObject({ status: 500, message: 'HTTP 500' })
  })
})
