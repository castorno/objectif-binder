import type { CardFinish } from '../../api/types'

/** The finishes a copy of a card can have, in the order they are listed. */
export const FINISHES: { value: CardFinish; label: string }[] = [
  { value: 'normal', label: 'Normale' },
  { value: 'holo', label: 'Holo' },
  { value: 'reverse', label: 'Reverse' },
]

export function finishLabel(finish: CardFinish): string {
  return FINISHES.find((candidate) => candidate.value === finish)?.label ?? finish
}

/**
 * The finishes to offer for a card, in the usual order. A card whose
 * finishes are unknown rules nothing out, as on the API side.
 */
export function finishesToOffer(finishes: CardFinish[] | null): CardFinish[] {
  const known = FINISHES.map((finish) => finish.value)

  return finishes === null || finishes.length === 0 ? known : known.filter((finish) => finishes.includes(finish))
}
