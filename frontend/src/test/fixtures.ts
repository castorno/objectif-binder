import type { CardDetail, CardSet, CardSummary, Game, Paginated, Rarity, User } from '../api/types'

export const demoUser: User = { id: 'user-1', email: 'camille@example.com' }
/** Not a secret: the made-up password the simulated API accepts for demoUser. */
export const demoPassword = 'correct horse battery staple'

export const demoGame: Game = { id: 'game-1', name: 'Jeu de démo', slug: 'demo' }

export const demoSets: CardSet[] = [
  { id: 'set-1', name: 'Aube', code: 'AUB', releaseDate: '2026-01-15' },
  { id: 'set-2', name: 'Crépuscule', code: 'CRE', releaseDate: null },
]

export const demoRarities: Rarity[] = [
  { id: 'rarity-1', name: 'Commune', sortOrder: 1 },
  { id: 'rarity-2', name: 'Rare', sortOrder: 2 },
]

export const emberFox: CardDetail = {
  id: 'card-1',
  name: 'Renard de braise',
  numberInSet: '012',
  rarity: 'Rare',
  setName: 'Aube',
  setCode: 'AUB',
  gameSlug: 'demo',
  externalId: null,
  attributes: { type: 'Feu', attaques: ['Griffe', 'Flammèche'] },
  pullOddsOneIn: 100,
}

export const mistOwl: CardDetail = {
  id: 'card-2',
  name: 'Chouette des brumes',
  numberInSet: '047',
  rarity: 'Commune',
  setName: 'Crépuscule',
  setCode: 'CRE',
  gameSlug: 'demo',
  externalId: null,
  attributes: {},
  pullOddsOneIn: null,
}

export const demoCards: CardDetail[] = [emberFox, mistOwl]

/** A page of search results; `meta` defaults to a single page holding exactly `cards`. */
export function cardPage(
  cards: CardSummary[],
  meta: Partial<Paginated<CardSummary>['meta']> = {},
): Paginated<CardSummary> {
  return {
    data: cards,
    meta: { total: cards.length, page: 1, limit: 20, totalPages: cards.length > 0 ? 1 : 0, ...meta },
  }
}
