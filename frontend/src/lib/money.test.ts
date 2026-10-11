import { describe, expect, it } from 'vitest'
import { formatEuros, parseEuros } from './money'

describe('formatEuros', () => {
  it('shows cents as euros, the French way', () => {
    expect(formatEuros(599).replace(/\s/g, ' ')).toBe('5,99 €')
    expect(formatEuros(600).replace(/\s/g, ' ')).toBe('6,00 €')
  })
})

describe('parseEuros', () => {
  it.each([
    ['5,99', 599],
    ['5.99', 599],
    ['6', 600],
    ['4,1', 410],
    [' 12,50 ', 1250],
    ['0,05', 5],
  ])('reads %s as %i cents', (text, cents) => {
    expect(parseEuros(text)).toBe(cents)
  })

  it.each(['', 'cher', '5,999', '-3', '5,', '1 000', '5€'])('refuses %j', (text) => {
    expect(parseEuros(text)).toBeNull()
  })
})
