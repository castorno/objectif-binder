import { http, HttpResponse } from 'msw'
import type { User } from '../api/types'
import { demoUser } from './fixtures'
import { server } from './server'

const ACCESS_TOKEN = 'test-access-token'

/**
 * Makes the API behave as for a browser holding a valid session: the refresh
 * cookie yields an access token, and that token identifies `user`. Mount the
 * application afterwards and it restores the session by itself, as it does
 * on a page reload.
 */
export function signInAs(user: User = demoUser) {
  server.use(
    http.post('*/api/auth/refresh', () => HttpResponse.json({ token: ACCESS_TOKEN })),
    http.get('*/api/me', ({ request }) =>
      request.headers.get('Authorization') === `Bearer ${ACCESS_TOKEN}`
        ? HttpResponse.json(user)
        : HttpResponse.json({ error: 'Authentication required.' }, { status: 401 }),
    ),
  )

  return user
}
