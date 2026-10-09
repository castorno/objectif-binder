import type { ReactNode } from 'react'
import type { CardSet } from '../../api/types'
import type { ComboboxOption } from '../../components/ComboboxField'
import { formatShortMonth } from '../../lib/dates'

/**
 * The sets of a game as options of a list: each with its date and code, and
 * each sub-set right under the set it comes in the boosters of, set back.
 */
export function setOptions(sets: CardSet[], mark?: (set: CardSet) => ReactNode): ComboboxOption[] {
  const codes = new Set(sets.map((set) => set.code))
  // A sub-set whose parent is not in the list stands on its own.
  const isSubSet = (set: CardSet) => set.parentCode !== null && codes.has(set.parentCode)

  return sets
    .filter((set) => !isSubSet(set))
    .flatMap((set) => [set, ...sets.filter((candidate) => candidate.parentCode === set.code)])
    .map((set) => ({
      value: set.code,
      label: set.name,
      detail: [set.releaseDate === null ? '' : formatShortMonth(set.releaseDate), set.code]
        .filter((part) => part !== '')
        .join(' · '),
      indented: isSubSet(set),
      mark: mark?.(set),
      // Also found by its code.
      keywords: set.code,
    }))
}
