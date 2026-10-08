export type Game = {
  id: string
  name: string
  slug: string
  /** What the game calls the identities its cards are grouped by; null when it has none. */
  identityLabel: string | null
}

export type CardSet = {
  id: string
  name: string
  code: string
  releaseDate: string | null
}

export type Rarity = {
  id: string
  name: string
  sortOrder: number
}

export type CardSummary = {
  id: string
  name: string
  numberInSet: string
  rarity: string | null
  setName: string
  setCode: string
  gameSlug: string
  /** Address of a picture of the card, served by a third party. Null for most cards. */
  imageUrl: string | null
}

/**
 * What cards of a game are grouped by: a creature that comes back from set to
 * set, or the rules card several printings share.
 */
export type CardIdentity = {
  id: string
  name: string
  /** Position in the game's own numbering, when it has one. */
  sortOrder: number | null
}

/** An identity as an entry of the grouped catalogue. */
export type CardIdentitySummary = CardIdentity & {
  gameSlug: string
  cardCount: number
  /** Picture of the first card of the identity that has one; see CardSummary.imageUrl. */
  imageUrl: string | null
}

export type IdentityPage = {
  data: CardIdentitySummary[]
  meta: Paginated<CardIdentitySummary>['meta'] & {
    /** Cards of the game no entry leads to. */
    cardsWithoutIdentity: number
  }
}

/** What the signed-in user owns of a page of the grouped catalogue. */
export type OwnedIdentities = {
  /** Identities matching the search, over all its pages. */
  totalIdentities: number
  /** Those of them the user owns at least one card of. */
  startedIdentities: number
  /** Number of owned cards by identity id; nothing for an identity the user owns no card of. */
  ownedByIdentity: Record<string, number>
  ownedWithoutIdentity: number
}

export type CardDetail = CardSummary & {
  /** The same picture, larger. */
  largeImageUrl: string | null
  identities: CardIdentity[]
  externalId: string | null
  attributes: Record<string, unknown>
  pullOddsOneIn: number | null
}

export type CardCondition = 'mint' | 'near_mint' | 'excellent' | 'good' | 'light_played' | 'played' | 'poor'

/** The copies of a card the user owns in one language. */
export type OwnedCard = {
  /** ISO 639-1 code, e.g. "fr". */
  language: string
  quantity: number
  /** Null when the user did not say. */
  condition: CardCondition | null
  acquiredAt: string
}

/** One card of the collection, with the copies owned in each language. */
export type CollectionEntry = {
  card: CardSummary
  owned: OwnedCard[]
}

/** How much of a catalogue search the user owns. */
export type CollectionCompletion = {
  /** Cards matching the search. */
  total: number
  /** Those of them the user owns, in any language. */
  owned: number
  /** What is owned of the cards of the requested page, by card id; nothing for a card not owned. */
  ownedOnPage: Record<string, OwnedCard[]>
}

export type User = {
  id: string
  email: string
}

export type Paginated<T> = {
  data: T[]
  meta: {
    total: number
    page: number
    limit: number
    totalPages: number
  }
}

/** Value of the `identity` filter asking for the cards that have none. */
export const WITHOUT_IDENTITY = 'none'

export type CatalogueView = '' | 'identities'

/** Narrows a search to what the signed-in user owns or lacks; empty for every card. */
export type Ownership = '' | 'owned' | 'missing'

export type CardSearchFilters = {
  q: string
  game: string
  set: string
  rarity: string
  /** Ignored for a visitor, who owns nothing. */
  ownership: Ownership
  /** Id of an identity, WITHOUT_IDENTITY for the cards that have none, or empty. */
  identity: string
  /** Not a filter: whether the catalogue lists cards, or one entry per identity. */
  view: CatalogueView
  page: number
}
