import { apiErrorFrom } from './errors'

/**
 * The access token (a JWT valid for 15 minutes) lives here, in memory, and
 * nowhere else: not in localStorage, not in a cookie scripts can read. A
 * script injected into the page could at worst use it until it expires; it
 * cannot take away something that lets it sign in again later.
 *
 * What does last is the refresh token, kept by the browser in an HttpOnly
 * cookie this code never sees. `refreshAccessToken()` asks the API to trade
 * it for a new access token, which is how a session survives a page reload.
 */
let accessToken: string | null = null
let pendingRefresh: Promise<string | null> | null = null

export function getAccessToken(): string | null {
  return accessToken
}

export function setAccessToken(token: string | null): void {
  accessToken = token
}

/**
 * Gets a new access token from the refresh cookie. Resolves to null when
 * there is no session to renew (never signed in, signed out, or expired),
 * and rejects when the API could not answer, which says nothing about the
 * session.
 *
 * Calls made while a refresh is under way share it: a refresh token only
 * works once, so sending it twice would make the second request fail.
 */
export function refreshAccessToken(): Promise<string | null> {
  pendingRefresh ??= acrossTabs(requestNewToken).finally(() => {
    pendingRefresh = null
  })

  return pendingRefresh
}

async function requestNewToken(): Promise<string | null> {
  // No Authorization header on purpose: the API rejects an expired access
  // token before it even looks at the cookie.
  const response = await fetch(new URL('/api/auth/refresh', window.location.origin), {
    method: 'POST',
    headers: { Accept: 'application/json' },
  })

  if (response.status === 401) {
    accessToken = null

    return null
  }
  if (!response.ok) throw await apiErrorFrom(response)

  const { token } = (await response.json()) as { token: string }
  accessToken = token

  return token
}

/**
 * Tabs of the same browser share the refresh cookie. Left alone, two tabs
 * refreshing at the same moment would send the same single-use token, and the
 * slower one would be signed out. The Web Locks API makes them take turns;
 * where it does not exist, the refresh simply runs unguarded.
 */
function acrossTabs<T>(task: () => Promise<T>): Promise<T> {
  if (typeof navigator === 'undefined' || navigator.locks === undefined) return task()

  return navigator.locks.request('objectif-binder:token-refresh', task) as Promise<T>
}
