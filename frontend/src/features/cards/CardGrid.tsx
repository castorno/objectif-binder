import type { CardSummary } from '../../api/types'
import { CardTile } from './CardTile'

const gridClasses = 'grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5'

export function CardGrid({ cards, dimmed = false }: { cards: CardSummary[]; dimmed?: boolean }) {
  return (
    <ul className={`${gridClasses} transition-opacity ${dimmed ? 'opacity-50' : ''}`}>
      {cards.map((card) => (
        <CardTile key={card.id} card={card} />
      ))}
    </ul>
  )
}

export function CardGridSkeleton({ count = 10 }: { count?: number }) {
  return (
    <div aria-hidden="true" className={gridClasses}>
      {Array.from({ length: count }, (_, index) => (
        <div key={index} className="flex flex-col gap-3 p-2 motion-safe:animate-pulse">
          <div className="aspect-[5/7] rounded-[6%/4.3%] bg-sunken" />
          <div className="h-4 w-3/4 rounded bg-sunken" />
          <div className="h-3 w-1/2 rounded bg-sunken" />
        </div>
      ))}
    </div>
  )
}
