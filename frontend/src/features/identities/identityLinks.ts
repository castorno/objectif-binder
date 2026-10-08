import type { CardSearchFilters } from '../../api/types'
import { filtersToSearchParams } from '../cards/useCardSearchParams'

const noFilters: CardSearchFilters = { q: '', game: '', set: '', rarity: '', ownership: '', identity: '', group: '', view: '', page: 1 }

/** The catalogue narrowed to the cards of an identity, or to those without any. */
export function identityCardsPath(identity: string, gameSlug: string): string {
  return `/?${filtersToSearchParams({ ...noFilters, game: gameSlug, identity })}`
}

/** The grouped catalogue of a game, or of every game. */
export function groupedCataloguePath(gameSlug: string, q = ''): string {
  return `/?${filtersToSearchParams({ ...noFilters, q, game: gameSlug, view: 'identities' })}`
}
