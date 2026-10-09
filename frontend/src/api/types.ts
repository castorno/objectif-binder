export type Game = {
  id: string
  name: string
  slug: string
  /** What the game calls the identities its cards are grouped by; null when it has none. */
  identityLabel: string | null
  /** What the game calls the groups its identities are sorted into; null when it has none. */
  identityGroupLabel: string | null
}

/** A family of identities of a game: an era its creatures appeared in, for instance. */
export type IdentityGroup = {
  name: string
  identityCount: number
}

export type CardSet = {
  id: string
  name: string
  code: string
  releaseDate: string | null
  /** Code of the set this one comes in the boosters of, if any. */
  parentCode: string | null
  /** Whether at least one card of the set has a picture. */
  hasPictures: boolean
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
  /** "YYYY-MM-DD"; null when the release date of the set is not known. */
  setReleaseDate: string | null
  /** The same picture, larger. */
  largeImageUrl: string | null
  identities: CardIdentity[]
  externalId: string | null
  attributes: Record<string, unknown>
  pullOddsOneIn: number | null
  /** Where the pull rate behind these odds comes from, when it was said. */
  pullOddsSource: string | null
}

/**
 * The estimated price of a card that is not graded, as a marketplace
 * reports it: every language and condition together. Amounts are in cents
 * of `currency`; any of them can be missing.
 */
export type CardPrice = {
  marketplace: string | null
  /** ISO 4217 code, e.g. "EUR". */
  currency: string | null
  /** What the card has been selling for lately. */
  trendCents: number | null
  /** The cheapest copy on sale. */
  lowCents: number | null
  average30DaysCents: number | null
  /** The same figures for the holographic version, when the marketplace tells them apart. */
  holoTrendCents: number | null
  holoLowCents: number | null
  holoAverage30DaysCents: number | null
  /** When the marketplace figures date from. */
  sourceUpdatedAt: string | null
  fetchedAt: string
  /** The page of the marketplace these figures are about. */
  productUrl: string | null
  /** Other cards of the set have the same name: the price may be that of another of them. */
  sharesNameInSet: boolean
}

/**
 * What adding a card started: it is the user's first card of these
 * identities. `started` and `total` say how far that takes them through the
 * identities of the game, which the game may name (`label`).
 */
export type Discovery = {
  identities: CardIdentity[]
  label: string | null
  started: number
  total: number
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
  /** Whether to show the way to the administration. The API checks the role itself. */
  isAdmin: boolean
}

/** So many cards for so many boosters: 4 for 1 is four in every booster, 1 for 8 one every eight. */
export type PullRate = { cards: number; boosters: number }

export type SetSummary = { id: string; name: string; code: string }

/** The pull rates of a set, as an administrator reads and enters them. */
export type SetPullRates = {
  set: SetSummary
  /** The set whose boosters hold the cards of this one: the rates are entered there. */
  parent: SetSummary | null
  /** The sets released in the boosters of this one: their cards count here. */
  subSets: SetSummary[]
  /** Where the figures come from, one answer for the whole set. */
  source: string | null
  updatedAt: string | null
  rarities: {
    id: string
    name: string
    /** How many cards of the set have this rarity. */
    cardsInSet: number
    /** How often a booster gives a card of this rarity; null when not entered. */
    rate: PullRate | null
  }[]
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
  /** Name of a group of identities; only narrows the grouped catalogue. */
  group: string
  /** Not a filter: whether the catalogue lists cards, or one entry per identity. */
  view: CatalogueView
  page: number
}
