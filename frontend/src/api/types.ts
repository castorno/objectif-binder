export type Game = {
  id: string
  name: string
  slug: string
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
}

export type CardDetail = CardSummary & {
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

export type CardSearchFilters = {
  q: string
  game: string
  set: string
  rarity: string
  page: number
}
