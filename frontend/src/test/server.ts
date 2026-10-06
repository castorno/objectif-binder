import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { cardPage, demoCards, demoGame, demoRarities, demoSets } from './fixtures'

/**
 * Stand-in for the API: a small catalogue that always answers successfully,
 * visited by someone who is not signed in.
 * A test needing something else (an error, an empty result, a look at the
 * request) overrides the route with `server.use(...)`; the override is
 * dropped after the test.
 */
const handlers = [
  http.get('*/api/games', () => HttpResponse.json([demoGame])),
  http.get('*/api/games/:slug/sets', () => HttpResponse.json(demoSets)),
  http.get('*/api/games/:slug/rarities', () => HttpResponse.json(demoRarities)),
  http.get('*/api/cards', () => HttpResponse.json(cardPage(demoCards))),
  http.get('*/api/cards/:id', ({ params }) => {
    const card = demoCards.find((candidate) => candidate.id === params.id)

    return card ? HttpResponse.json(card) : HttpResponse.json({ error: 'Card not found.' }, { status: 404 })
  }),
  // Nobody is signed in unless a test says so with signInAs() (see session.ts).
  http.post('*/api/auth/refresh', () =>
    HttpResponse.json({ error: 'Invalid or expired refresh token.' }, { status: 401 }),
  ),
  http.get('*/api/me', () => HttpResponse.json({ error: 'Authentication required.' }, { status: 401 })),
  http.post('*/api/auth/logout', () => new HttpResponse(null, { status: 204 })),
]

export const server = setupServer(...handlers)
