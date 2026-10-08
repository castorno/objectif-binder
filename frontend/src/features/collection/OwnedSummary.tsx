import type { OwnedCard } from '../../api/types'
import { languageLabel } from './languages'

/** What the user owns of a card, on its tile: e.g. "FR ×2" and "JA ×1". */
export function OwnedSummary({ owned }: { owned: OwnedCard[] }) {
  return (
    <p className="mt-1 flex flex-wrap gap-1">
      {owned.map((entry) => (
        <span key={entry.language} className="rounded-md bg-accent-soft px-1.5 py-0.5 text-xs font-semibold tabular-nums">
          <span aria-hidden="true">
            {entry.language.toUpperCase()} ×{entry.quantity}
          </span>
          <span className="sr-only">
            {languageLabel(entry.language)} : {entry.quantity} {entry.quantity > 1 ? 'exemplaires' : 'exemplaire'}
          </span>
        </span>
      ))}
    </p>
  )
}
