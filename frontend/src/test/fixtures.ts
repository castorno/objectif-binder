import type {
  CardDetail,
  CardIdentitySummary,
  CardSet,
  CardSummary,
  Game,
  IdentityPage,
  Paginated,
  Rarity,
  User,
} from '../api/types'

export const demoUser: User = { id: 'user-1', email: 'camille@example.com' }
/** Not a secret: the made-up password the simulated API accepts for demoUser. */
export const demoPassword = 'correct horse battery staple'

export const demoGame: Game = { id: 'game-1', name: 'Jeu de démo', slug: 'demo', identityLabel: 'Créatures' }

/** What the demo cards are grouped by: the fox comes back in two cards, the owl in one. */
export const foxIdentity: CardIdentitySummary = {
  id: '11111111-1111-4111-8111-111111111111',
  name: 'Renard',
  sortOrder: 1,
  gameSlug: 'demo',
  cardCount: 2,
}

export const owlIdentity: CardIdentitySummary = {
  id: '22222222-2222-4222-8222-222222222222',
  name: 'Chouette',
  sortOrder: 2,
  gameSlug: 'demo',
  cardCount: 1,
}

export const demoIdentities: CardIdentitySummary[] = [foxIdentity, owlIdentity]

/** A page of the grouped catalogue; `meta` defaults to a single page and three cards without identity. */
export function identityPage(
  identities: CardIdentitySummary[],
  meta: Partial<IdentityPage['meta']> = {},
): IdentityPage {
  return {
    data: identities,
    meta: {
      total: identities.length,
      page: 1,
      limit: 20,
      totalPages: identities.length > 0 ? 1 : 0,
      cardsWithoutIdentity: 3,
      ...meta,
    },
  }
}

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
  identities: [{ id: foxIdentity.id, name: foxIdentity.name, sortOrder: foxIdentity.sortOrder }],
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
  identities: [],
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
