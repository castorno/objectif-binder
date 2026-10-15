import { DEFAULT_PAGE_SIZE } from '../../lib/pagination'
import { filtersToSearchParams, noFilters } from '../cards/useCardSearchParams'

/** The catalogue narrowed to the cards of an identity, or to those without any. */
export function identityCardsPath(identity: string, gameSlug: string): string {
  return `/?${filtersToSearchParams({ ...noFilters, game: gameSlug, identity })}`
}

/** The grouped catalogue of a game, or of every game. */
export function groupedCataloguePath(gameSlug: string, q = '', limit = DEFAULT_PAGE_SIZE): string {
  return `/?${filtersToSearchParams({ ...noFilters, q, game: gameSlug, view: 'identities', limit })}`
}
