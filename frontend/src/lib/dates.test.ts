import { describe, expect, it } from 'vitest'
import { formatLongMonth, formatShortMonth } from './dates'

describe('release dates', () => {
  it('shows the month and the year', () => {
    expect(formatShortMonth('2003-11-24')).toBe('nov. 2003')
    expect(formatLongMonth('2003-11-24')).toBe('novembre 2003')
  })

  it('keeps the first day of a month in that month, whatever the time zone', () => {
    expect(formatLongMonth('1999-01-01')).toBe('janvier 1999')
  })

  it('shows nothing for a date it cannot read', () => {
    expect(formatShortMonth('soon')).toBe('')
    expect(formatLongMonth('')).toBe('')
  })
})
