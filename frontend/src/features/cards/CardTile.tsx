import type { ReactNode } from 'react'
import { Link, useLocation } from 'react-router'
import type { CardSummary } from '../../api/types'
import { CardArt } from './CardArt'
import { RarityBadge } from './RarityBadge'

/** `footer` is shown under the card's details, e.g. what the user owns of it. */
export function CardTile({ card, footer }: { card: CardSummary; footer?: ReactNode }) {
  const location = useLocation()

  return (
    <li>
      <Link
        to={`/cards/${card.id}`}
        // Lets the detail page link back to this exact search.
        state={{ fromSearch: location.search }}
        className="group flex h-full flex-col gap-3 rounded-xl p-2 transition-colors hover:bg-surface"
      >
        <CardArt
          name={card.name}
          setCode={card.setCode}
          numberInSet={card.numberInSet}
          className="text-sm transition-transform duration-200 motion-safe:group-hover:-translate-y-1"
        />
        <div className="flex flex-col items-start gap-1 px-1 pb-1">
          <h3 className="leading-snug font-medium">{card.name}</h3>
          <p className="text-sm text-muted">
            {card.setName} · n° {card.numberInSet}
          </p>
          <RarityBadge rarity={card.rarity} />
          {footer}
        </div>
      </Link>
    </li>
  )
}
