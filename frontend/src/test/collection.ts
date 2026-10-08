import { http, HttpResponse } from 'msw'
import type { CardCondition, CollectionEntry, Discovery, OwnedCard } from '../api/types'
import { demoCards } from './fixtures'
import { server } from './server'

type Change = { method: 'PUT' | 'DELETE'; cardId: string; language: string; body?: unknown }

export function ownedCard(language: string, quantity = 1, condition: CardCondition | null = null): OwnedCard {
  return { language, quantity, condition, acquiredAt: '2026-10-01T10:00:00+00:00' }
}

/**
 * Makes the API hold a collection for the signed-in user, given as the owned
 * entries of each card id, and keep it up to date as the application changes
 * it. Returns the changes the API received, and the URLs the owned list, the
 * missing list and the completion rate were asked with.
 */
/**
 * @param discoveries what the API says adding a card started, by card id;
 *                    said once, for the first copy of the card
 */
export function haveCollection(initial: Record<string, OwnedCard[]> = {}, discoveries: Record<string, Discovery> = {}) {
  const collection = new Map(Object.entries(initial))
  const changes: Change[] = []
  const listRequests: URL[] = []
  const completionRequests: URL[] = []
  const missingRequests: URL[] = []

  server.use(
    http.get('*/api/collection', ({ request }) => {
      listRequests.push(new URL(request.url))
      const data: CollectionEntry[] = demoCards
        .filter((card) => (collection.get(card.id) ?? []).length > 0)
        .map((card) => ({ card, owned: collection.get(card.id) ?? [] }))

      return HttpResponse.json({
        data,
        meta: { total: data.length, page: 1, limit: 20, totalPages: data.length > 0 ? 1 : 0 },
      })
    }),
    http.get('*/api/collection/missing', ({ request }) => {
      missingRequests.push(new URL(request.url))
      const data = demoCards.filter((card) => (collection.get(card.id) ?? []).length === 0)

      return HttpResponse.json({
        data,
        meta: { total: data.length, page: 1, limit: 20, totalPages: data.length > 0 ? 1 : 0 },
      })
    }),
    http.get('*/api/collection/completion', ({ request }) => {
      completionRequests.push(new URL(request.url))
      const ownedOnPage = Object.fromEntries(
        demoCards
          .filter((card) => (collection.get(card.id) ?? []).length > 0)
          .map((card) => [card.id, collection.get(card.id) ?? []]),
      )

      return HttpResponse.json({ total: demoCards.length, owned: Object.keys(ownedOnPage).length, ownedOnPage })
    }),
    http.get('*/api/collection/cards/:id', ({ params }) =>
      HttpResponse.json({ data: collection.get(String(params.id)) ?? [] }),
    ),
    http.put('*/api/collection/cards/:id/:language', async ({ params, request }) => {
      const cardId = String(params.id)
      const language = String(params.language)
      const body = (await request.json()) as { quantity: number; condition: CardCondition | null }
      changes.push({ method: 'PUT', cardId, language, body })

      const others = (collection.get(cardId) ?? []).filter((owned) => owned.language !== language)
      const existed = others.length !== (collection.get(cardId) ?? []).length
      const saved = ownedCard(language, body.quantity, body.condition)
      collection.set(cardId, [...others, saved])

      const discovery = !existed && others.length === 0 ? (discoveries[cardId] ?? null) : null

      return HttpResponse.json({ ...saved, discovery }, { status: existed ? 200 : 201 })
    }),
    http.delete('*/api/collection/cards/:id/:language', ({ params }) => {
      const cardId = String(params.id)
      const language = String(params.language)
      changes.push({ method: 'DELETE', cardId, language })
      collection.set(cardId, (collection.get(cardId) ?? []).filter((owned) => owned.language !== language))

      return new HttpResponse(null, { status: 204 })
    }),
  )

  return { changes, listRequests, completionRequests, missingRequests }
}
