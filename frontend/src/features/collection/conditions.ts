import type { CardCondition } from '../../api/types'

/**
 * From best to worst. The labels stay in English on purpose: these are the
 * terms collectors and marketplaces use, in French too.
 */
export const CONDITIONS: { value: CardCondition; label: string }[] = [
  { value: 'mint', label: 'Mint' },
  { value: 'near_mint', label: 'Near Mint' },
  { value: 'excellent', label: 'Excellent' },
  { value: 'good', label: 'Good' },
  { value: 'light_played', label: 'Light Played' },
  { value: 'played', label: 'Played' },
  { value: 'poor', label: 'Poor' },
]
