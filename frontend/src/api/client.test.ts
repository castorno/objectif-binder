import { http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it } from 'vitest'
import { server } from '../test/server'
import { getAccessToken, setAccessToken } from './accessToken'
import { ApiError, apiGet, apiPost, apiRequest } from './client'

/** Makes the API answer `respond` on the route and records the requests it got. */
function record(method: 'get' | 'post', path: string, respond: (request: Request) => Response = () => HttpResponse.json({})) {
  const requests: Request[] = []
  server.use(
    http[method](`*${path}`, ({ request }) => {
      requests.push(request.clone())

      return respond(request)
    }),
  )

  return requests
}

/** A protected route that only accepts the given access token. */
function protectedRoute(validToken: string) {
  return record('get', '/api/me', (request) =>
    request.headers.get('Authorization') === `Bearer ${validToken}`
      ? HttpResponse.json({ id: '1', email: 'user@example.com' })
      : HttpResponse.json({ error: 'Expired token.' }, { status: 401 }),
  )
}

/** The refresh endpoint, answering with `token`, or 401 when there is no session. */
function refreshRoute(token: string | null) {
  return record('post', '/api/auth/refresh', () =>
    token === null
      ? HttpResponse.json({ error: 'Invalid or expired refresh token.' }, { status: 401 })
      : HttpResponse.json({ token }),
  )
}

afterEach(() => {
  setAccessToken(null)
})

describe('apiGet', () => {
  it('returns the decoded JSON body', async () => {
    record('get', '/api/games', () => HttpResponse.json([{ id: '1', name: 'Demo', slug: 'demo' }]))

    await expect(apiGet('/api/games')).resolves.toEqual([{ id: '1', name: 'Demo', slug: 'demo' }])
  })

  it('requests the path on the origin of the page with the query parameters', async () => {
    const requests = record('get', '/api/cards')

    await apiGet('/api/cards', { q: 'feu & glace', page: 2 })

    const url = new URL(requests[0].url)
    expect(url.origin).toBe(window.location.origin)
    expect(url.pathname).toBe('/api/cards')
    expect(url.searchParams.get('q')).toBe('feu & glace')
    expect(url.searchParams.get('page')).toBe('2')
  })

  it('leaves out empty and undefined parameters', async () => {
    const requests = record('get', '/api/cards')

    await apiGet('/api/cards', { q: '', game: undefined, set: 'AAA' })

    expect([...new URL(requests[0].url).searchParams.keys()]).toEqual(['set'])
  })

  it('asks for JSON', async () => {
    const requests = record('get', '/api/games')

    await apiGet('/api/games')

    expect(requests[0].headers.get('Accept')).toBe('application/json')
  })

  it('can be cancelled through the abort signal', async () => {
    record('get', '/api/games')
    const controller = new AbortController()
    controller.abort()

    await expect(apiGet('/api/games', {}, controller.signal)).rejects.toMatchObject({ name: 'AbortError' })
  })

  it('never sends the access token to a public route, even when signed in', async () => {
    // The API rejects an expired token even on routes that need none.
    setAccessToken('some-token')
    const requests = record('get', '/api/games')

    await apiGet('/api/games')

    expect(requests[0].headers.has('Authorization')).toBe(false)
  })
})

describe('apiPost', () => {
  it('sends the body as JSON', async () => {
    const requests = record('post', '/api/auth/register', () => HttpResponse.json({ id: '1' }, { status: 201 }))

    await apiPost('/api/auth/register', { email: 'user@example.com', password: 'secret' })

    expect(requests[0].headers.get('Content-Type')).toBe('application/json')
    await expect(requests[0].json()).resolves.toEqual({ email: 'user@example.com', password: 'secret' })
  })

  it('resolves to nothing for an empty response', async () => {
    record('post', '/api/auth/logout', () => new HttpResponse(null, { status: 204 }))

    await expect(apiPost('/api/auth/logout')).resolves.toBeUndefined()
  })
})

describe('errors', () => {
  it('throws an ApiError carrying the status and the message sent by the API', async () => {
    record('get', '/api/cards/unknown', () => HttpResponse.json({ error: 'Card not found.' }, { status: 404 }))

    const error = await apiGet('/api/cards/unknown').catch((reason: unknown) => reason)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ name: 'ApiError', status: 404, message: 'Card not found.', violations: {} })
  })

  it('exposes the messages per field of a validation failure', async () => {
    const violations = { email: ['This value is not a valid email address.'], password: ['Too short.', 'Too weak.'] }
    record('post', '/api/auth/register', () =>
      HttpResponse.json({ error: 'Validation failed.', violations }, { status: 422 }),
    )

    await expect(apiPost('/api/auth/register', {})).rejects.toMatchObject({ status: 422, violations })
  })

  it('falls back to the HTTP status when the error body is not JSON', async () => {
    record('get', '/api/cards', () => new HttpResponse('<html>Bad Gateway</html>', { status: 502 }))

    await expect(apiGet('/api/cards')).rejects.toMatchObject({ status: 502, message: 'HTTP 502' })
  })

  it('falls back to the HTTP status when the error body has no usable message', async () => {
    record('get', '/api/cards', () => HttpResponse.json({ error: { code: 42 } }, { status: 500 }))

    await expect(apiGet('/api/cards')).rejects.toMatchObject({ status: 500, message: 'HTTP 500' })
  })
})

describe('authenticated requests', () => {
  it('sends the access token as a bearer token', async () => {
    setAccessToken('valid-token')
    const requests = protectedRoute('valid-token')

    await expect(apiRequest('/api/me', { auth: true })).resolves.toEqual({ id: '1', email: 'user@example.com' })
    expect(requests).toHaveLength(1)
  })

  it('gets a token from the refresh cookie first when it holds none, as after a page reload', async () => {
    const requests = protectedRoute('fresh-token')
    const refreshes = refreshRoute('fresh-token')

    await expect(apiRequest('/api/me', { auth: true })).resolves.toMatchObject({ email: 'user@example.com' })

    expect(refreshes).toHaveLength(1)
    expect(requests).toHaveLength(1)
  })

  it('renews an expired token and replays the request, unnoticed by the caller', async () => {
    setAccessToken('expired-token')
    const requests = protectedRoute('fresh-token')
    const refreshes = refreshRoute('fresh-token')

    await expect(apiRequest('/api/me', { auth: true })).resolves.toMatchObject({ email: 'user@example.com' })

    expect(refreshes).toHaveLength(1)
    expect(requests.map((request) => request.headers.get('Authorization'))).toEqual([
      'Bearer expired-token',
      'Bearer fresh-token',
    ])
    expect(getAccessToken()).toBe('fresh-token')
  })

  it('refreshes once when several requests find the token expired together', async () => {
    // A refresh token works a single time: three refreshes would mean two failures.
    setAccessToken('expired-token')
    protectedRoute('fresh-token')
    const refreshes = refreshRoute('fresh-token')

    const results = await Promise.all([
      apiRequest('/api/me', { auth: true }),
      apiRequest('/api/me', { auth: true }),
      apiRequest('/api/me', { auth: true }),
    ])

    expect(results).toHaveLength(3)
    expect(refreshes).toHaveLength(1)
  })

  it('does not send the access token to the refresh endpoint', async () => {
    setAccessToken('expired-token')
    protectedRoute('fresh-token')
    const refreshes = refreshRoute('fresh-token')

    await apiRequest('/api/me', { auth: true })

    expect(refreshes[0].headers.has('Authorization')).toBe(false)
  })

  it('fails with a 401 and forgets the token when the session cannot be renewed', async () => {
    setAccessToken('expired-token')
    const requests = protectedRoute('fresh-token')
    refreshRoute(null)

    await expect(apiRequest('/api/me', { auth: true })).rejects.toMatchObject({ status: 401 })

    expect(requests).toHaveLength(1)
    expect(getAccessToken()).toBeNull()
  })

  it('does not call the protected route at all when nobody is signed in', async () => {
    const requests = protectedRoute('fresh-token')
    refreshRoute(null)

    await expect(apiRequest('/api/me', { auth: true })).rejects.toMatchObject({ status: 401 })

    expect(requests).toHaveLength(0)
  })

  it('gives up after one replay rather than looping on a route that keeps answering 401', async () => {
    setAccessToken('expired-token')
    const requests = protectedRoute('a-token-the-api-never-issues')
    const refreshes = refreshRoute('fresh-token')

    await expect(apiRequest('/api/me', { auth: true })).rejects.toMatchObject({ status: 401 })

    expect(requests).toHaveLength(2)
    expect(refreshes).toHaveLength(1)
  })

  it('does not refresh on errors other than 401', async () => {
    setAccessToken('valid-token')
    record('get', '/api/me', () => HttpResponse.json({ error: 'Access Denied.' }, { status: 403 }))
    const refreshes = refreshRoute('fresh-token')

    await expect(apiRequest('/api/me', { auth: true })).rejects.toMatchObject({ status: 403 })

    expect(refreshes).toHaveLength(0)
  })
})
