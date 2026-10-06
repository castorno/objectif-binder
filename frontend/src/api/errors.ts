/** Messages per field, as sent by the API when a request body fails validation. */
export type Violations = Record<string, string[]>

export class ApiError extends Error {
  readonly status: number
  readonly violations: Violations

  constructor(status: number, message: string, violations: Violations = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.violations = violations
  }
}

/**
 * Builds the error for a failed response. The API always answers errors as
 * {"error": "..."}; the status is used instead if something in front of it
 * (proxy, dev server) answered.
 */
export async function apiErrorFrom(response: Response): Promise<ApiError> {
  const body: unknown = await response.json().catch(() => null)
  const fields = typeof body === 'object' && body !== null ? (body as Record<string, unknown>) : {}

  const message = typeof fields.error === 'string' ? fields.error : `HTTP ${response.status}`
  const violations =
    typeof fields.violations === 'object' && fields.violations !== null ? (fields.violations as Violations) : {}

  return new ApiError(response.status, message, violations)
}
