import { describe, expect, it } from 'vitest'
import { matchesSearch, normalizeForSearch } from './textSearch'

describe('normalizeForSearch', () => {
  it('drops accents and capitals', () => {
    expect(normalizeForSearch('Écarlate et Violet')).toBe('ecarlate et violet')
    expect(normalizeForSearch('Ténèbres Embrasées')).toBe('tenebres embrasees')
  })
})

describe('matchesSearch', () => {
  it('finds a word anywhere in the text', () => {
    expect(matchesSearch('EX Dragon', 'dragon')).toBe(true)
    expect(matchesSearch('Majesté des Dragons', 'dragon')).toBe(true)
    expect(matchesSearch('Set de Base', 'dragon')).toBe(false)
  })

  it('ignores accents and capitals on both sides', () => {
    expect(matchesSearch('Écarlate et Violet', 'ecarlate')).toBe(true)
    expect(matchesSearch('Ecarlate et Violet', 'ÉCARLATE')).toBe(true)
  })

  it('wants every word, in any order', () => {
    expect(matchesSearch('EX Dragon', 'dragon ex')).toBe(true)
    expect(matchesSearch('Majesté des Dragons', 'ex dragon')).toBe(false)
  })

  it('matches everything on an empty query', () => {
    expect(matchesSearch('EX Dragon', '')).toBe(true)
    expect(matchesSearch('EX Dragon', '   ')).toBe(true)
  })
})
