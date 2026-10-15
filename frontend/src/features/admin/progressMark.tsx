import type { ReactNode } from 'react'
import type { SetProgress } from '../../api/types'

/**
 * What is entered for a set, after its name in the list: a tick once the
 * price and every rate are, a half circle while one of them is missing,
 * nothing before anything is.
 */
export function progressMark({ hasBoosterPrice, hasCompletePullRates }: SetProgress): ReactNode {
  if (!hasBoosterPrice && !hasCompletePullRates) return undefined

  const done = hasBoosterPrice && hasCompletePullRates
  const text = done ? 'prix et taux saisis' : hasBoosterPrice ? 'taux à compléter' : 'prix à saisir'

  return (
    <span title={text[0].toUpperCase() + text.slice(1)} className={`inline-block align-text-bottom ${done ? 'text-accent' : 'text-muted'}`}>
      <svg aria-hidden="true" viewBox="0 0 16 16" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="8" cy="8" r="6.25" />
        {done ? <path d="M5.25 8.25l1.75 1.75 3.75-4" /> : <path d="M8 1.75a6.25 6.25 0 0 1 0 12.5z" fill="currentColor" stroke="none" />}
      </svg>
      <span className="sr-only">{text}</span>
    </span>
  )
}
