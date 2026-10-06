export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

type QueryParams = Record<string, string | number | undefined>

export async function apiGet<T>(path: string, params: QueryParams = {}, signal?: AbortSignal): Promise<T> {
  // Same origin as the page: the server behind it relays /api to the API.
  const url = new URL(path, window.location.origin)
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') {
      url.searchParams.set(key, String(value))
    }
  }

  const response = await fetch(url, { signal, headers: { Accept: 'application/json' } })

  if (!response.ok) {
    // The API always answers errors as {"error": "..."}; fall back to the
    // status if something in front of it (proxy, dev server) answered instead.
    const body: unknown = await response.json().catch(() => null)
    const message =
      typeof body === 'object' && body !== null && 'error' in body && typeof body.error === 'string'
        ? body.error
        : `HTTP ${response.status}`
    throw new ApiError(response.status, message)
  }

  return response.json() as Promise<T>
}
