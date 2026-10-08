import { getAccessToken, refreshAccessToken } from './accessToken'
import { ApiError, apiErrorFrom } from './errors'

export { ApiError } from './errors'

type QueryParams = Record<string, string | number | undefined>

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE'
  params?: QueryParams
  /** Sent as JSON. */
  body?: unknown
  signal?: AbortSignal
  /**
   * Send the access token, renewing it when needed. Off by default: public
   * routes must be called without one, because the API rejects a request
   * carrying an expired token even where no token is required.
   */
  auth?: boolean
}

export function apiGet<T>(path: string, params: QueryParams = {}, signal?: AbortSignal): Promise<T> {
  return apiRequest<T>(path, { params, signal })
}

export function apiPost<T>(path: string, body?: unknown, options: Pick<RequestOptions, 'auth' | 'signal'> = {}): Promise<T> {
  return apiRequest<T>(path, { ...options, method: 'POST', body })
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  if (!options.auth) return read<T>(await send(path, options))

  const token = getAccessToken() ?? (await refreshAccessToken())
  if (token === null) throw new ApiError(401, 'Not signed in.')

  let response = await send(path, options, token)

  if (response.status === 401) {
    // Most likely the token expired. Renew it and replay the request, once.
    // If another request renewed it in the meantime, use that token rather
    // than spending a second refresh.
    const current = getAccessToken()
    const renewed = current !== null && current !== token ? current : await refreshAccessToken()
    if (renewed !== null) response = await send(path, options, renewed)
  }

  return read<T>(response)
}

function send(path: string, { method = 'GET', params = {}, body, signal }: RequestOptions, token?: string): Promise<Response> {
  // Same origin as the page: the server behind it relays /api to the API.
  const url = new URL(path, window.location.origin)
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') {
      url.searchParams.set(key, String(value))
    }
  }

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token !== undefined) headers.Authorization = `Bearer ${token}`

  return fetch(url, {
    method,
    signal,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
}

async function read<T>(response: Response): Promise<T> {
  if (!response.ok) throw await apiErrorFrom(response)
  if (response.status === 204) return undefined as T

  return response.json() as Promise<T>
}
