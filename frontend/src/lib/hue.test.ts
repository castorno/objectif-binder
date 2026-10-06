import { describe, expect, it } from 'vitest'
import { hueFromString } from './hue'

describe('hueFromString', () => {
  it('always gives the same hue for the same string', () => {
    expect(hueFromString('Ember Fox')).toBe(hueFromString('Ember Fox'))
  })

  it('gives different hues to different strings', () => {
    expect(hueFromString('a')).toBe(97)
    expect(hueFromString('b')).toBe(98)
  })

  it('stays within the hue range, including for long strings that overflow the hash', () => {
    for (const value of ['', 'a', 'Ember Fox', 'x'.repeat(500), 'Élan ✨ 日本語']) {
      const hue = hueFromString(value)

      expect(Number.isInteger(hue)).toBe(true)
      expect(hue).toBeGreaterThanOrEqual(0)
      expect(hue).toBeLessThan(360)
    }
  })
})
