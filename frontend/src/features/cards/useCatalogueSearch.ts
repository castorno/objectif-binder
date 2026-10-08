import { useQuery } from '@tanstack/react-query'
import { cardSearchQuery, collectionSearchQuery, missingCardsQuery } from '../../api/queries'
import type { CardSearchFilters, Ownership } from '../../api/types'
import { useSession } from '../auth/useSession'

/**
 * The cards of the catalogue for a search. Every card comes from the public
 * route; what the signed-in user owns or lacks comes from their collection,
 * which answers in the same shape. Only one of the three is asked at a time.
 */
export function useCatalogueSearch(filters: CardSearchFilters) {
  const { user, isPending: isSessionPending } = useSession()
  // A visitor owns nothing: the filter, possibly left in a shared URL, is ignored.
  const ownership: Ownership = user === null ? '' : filters.ownership
  // On a page reload the session is not known yet. Wait for it rather than
  // show every card for an instant to someone who asked for the missing ones.
  const isWaitingForSession = isSessionPending && filters.ownership !== ''

  const all = useQuery({ ...cardSearchQuery(filters), enabled: ownership === '' && !isWaitingForSession })
  const owned = useQuery({
    ...collectionSearchQuery(filters),
    enabled: ownership === 'owned',
    select: (page) => ({ data: page.data.map((entry) => entry.card), meta: page.meta }),
  })
  const missing = useQuery({ ...missingCardsQuery(filters), enabled: ownership === 'missing' })

  const search = ownership === 'owned' ? owned : ownership === 'missing' ? missing : all

  return { search, ownership }
}
