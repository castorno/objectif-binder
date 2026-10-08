import { Link } from 'react-router'
import { CardArt } from '../cards/CardArt'

const countFormatter = new Intl.NumberFormat('fr-FR')

type IdentityTileProps = {
  name: string
  /** Position in the game's own numbering, when it has one. */
  sortOrder?: number | null
  cardCount: number
  /** Cards of it the signed-in user owns; left out for a visitor. */
  owned?: number
  to: string
}

/** One entry of the grouped catalogue: a stack of cards standing for all the cards of an identity. */
export function IdentityTile({ name, sortOrder = null, cardCount, owned, to }: IdentityTileProps) {
  return (
    <li>
      <Link to={to} className="group flex h-full flex-col gap-3 rounded-xl p-2 transition-colors hover:bg-surface">
        <div className="relative transition-transform duration-200 motion-safe:group-hover:-translate-y-1">
          {/* Two card backs peeking out: this entry holds several cards. */}
          <span aria-hidden="true" className="absolute inset-0 translate-x-1.5 -translate-y-1 rotate-3 rounded-[6%/4.3%] bg-line" />
          <span
            aria-hidden="true"
            className="absolute inset-0 -translate-x-1 -translate-y-0.5 -rotate-2 rounded-[6%/4.3%] bg-sunken ring-1 ring-line"
          />
          <CardArt
            name={name}
            setCode=""
            numberInSet={sortOrder === null ? '' : String(sortOrder).padStart(3, '0')}
            className="relative text-sm"
          />
        </div>

        <div className="flex flex-col gap-1 px-1 pb-1">
          <h3 className="leading-snug font-medium">{name}</h3>
          <p className="text-sm text-muted">
            {countFormatter.format(cardCount)} {cardCount > 1 ? 'cartes' : 'carte'}
          </p>
          {owned !== undefined && cardCount > 0 && (
            <div className="mt-1 flex items-center gap-2">
              <div aria-hidden="true" className="h-1.5 flex-1 overflow-hidden rounded-full bg-sunken">
                <div style={{ width: `${(owned / cardCount) * 100}%` }} className="h-full rounded-full bg-accent" />
              </div>
              <p className="font-display text-xs font-semibold tabular-nums">
                {countFormatter.format(owned)} / {countFormatter.format(cardCount)}
                <span className="sr-only"> {owned > 1 ? 'possédées' : 'possédée'}</span>
              </p>
            </div>
          )}
        </div>
      </Link>
    </li>
  )
}
