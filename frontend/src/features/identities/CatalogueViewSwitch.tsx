import { Link } from 'react-router'
import type { CardSearchFilters } from '../../api/types'
import { filtersToSearchParams } from '../cards/useCardSearchParams'
import { groupedCataloguePath } from './identityLinks'

const optionClasses =
  'rounded-md px-3 py-1.5 text-sm font-medium transition-colors aria-[current=page]:bg-accent aria-[current=page]:text-on-accent'

type CatalogueViewSwitchProps = {
  filters: CardSearchFilters
  /** What the selected game calls its identities, e.g. "Créatures"; a generic word otherwise. */
  identityLabel?: string | null
}

/** Chooses between one entry per card and one entry per identity. */
export function CatalogueViewSwitch({ filters, identityLabel }: CatalogueViewSwitchProps) {
  const isGrouped = filters.view === 'identities'

  return (
    <nav aria-label="Affichage du catalogue" className="inline-flex gap-1 rounded-lg border border-line bg-surface p-1">
      <Link
        // The name typed and the game carry over; what only one view knows does not.
        to={`/?${filtersToSearchParams({ ...filters, view: '', page: 1 })}`}
        aria-current={isGrouped ? undefined : 'page'}
        className={optionClasses}
      >
        Cartes
      </Link>
      <Link to={groupedCataloguePath(filters.game, filters.q)} aria-current={isGrouped ? 'page' : undefined} className={optionClasses}>
        {identityLabel ?? 'Regroupées'}
      </Link>
    </nav>
  )
}
