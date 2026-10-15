import { describe, expect, it } from 'vitest'
import { countLabel, formatCount, plural } from './counts'

describe('counts', () => {
  it('separates thousands the French way', () => {
    expect(formatCount(1234)).toMatch(/^1\s234$/)
  })

  it('takes the singular for 0 and 1, the plural above', () => {
    expect(plural(0, 'carte', 'cartes')).toBe('carte')
    expect(plural(1, 'carte', 'cartes')).toBe('carte')
    expect(plural(2, 'carte', 'cartes')).toBe('cartes')
  })

  it('puts the count before its word', () => {
    expect(countLabel(1234, 'carte', 'cartes')).toMatch(/^1\s234 cartes$/)
  })
})
