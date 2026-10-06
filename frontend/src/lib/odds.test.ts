import { describe, expect, it } from 'vitest'
import { oddsToPercent, triesForProbability } from './odds'

describe('oddsToPercent', () => {
  it('converts "1 in N" odds to a percentage', () => {
    expect(oddsToPercent(4)).toBe(25)
    expect(oddsToPercent(1)).toBe(100)
    expect(oddsToPercent(200)).toBe(0.5)
  })
})

describe('triesForProbability', () => {
  it('defaults to an even chance', () => {
    // 1 - (99/100)^68 = 49.5 %, 1 - (99/100)^69 = 50.02 %
    expect(triesForProbability(100)).toBe(69)
  })

  it('needs a single try when one try already reaches the target', () => {
    expect(triesForProbability(2)).toBe(1)
  })

  it('needs more tries for a higher target', () => {
    // 1 - (9/10)^21 = 89.1 %, 1 - (9/10)^22 = 90.2 %
    expect(triesForProbability(10, 0.9)).toBe(22)
  })

  it('answers one try for a certain event instead of dividing by ln(0)', () => {
    expect(triesForProbability(1)).toBe(1)
    expect(triesForProbability(0.5)).toBe(1)
  })
})
