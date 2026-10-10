import type { OwnedCard } from '../../api/types'
import { languageLabel } from './languages'

/**
 * What the user owns of a card, on its tile: e.g. "FR ×2" and "JA ×1". One
 * figure per language, all finishes together: which finish is which is on
 * the page of the card.
 */
export function OwnedSummary({ owned }: { owned: OwnedCard[] }) {
  const quantities = new Map<string, number>()
  for (const entry of owned) {
    quantities.set(entry.language, (quantities.get(entry.language) ?? 0) + entry.quantity)
  }

  return (
    <p className="mt-1 flex flex-wrap gap-1">
      {[...quantities].map(([language, quantity]) => (
        <span key={language} className="rounded-md bg-accent-soft px-1.5 py-0.5 text-xs font-semibold tabular-nums">
          <span aria-hidden="true">
            {language.toUpperCase()} ×{quantity}
          </span>
          <span className="sr-only">
            {languageLabel(language)} : {quantity} {quantity > 1 ? 'exemplaires' : 'exemplaire'}
          </span>
        </span>
      ))}
    </p>
  )
}
