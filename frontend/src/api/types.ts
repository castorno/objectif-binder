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
