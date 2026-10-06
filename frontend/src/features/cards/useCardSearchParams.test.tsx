import { describe, expect, it } from 'vitest'
import type { CardSearchFilters } from '../../api/types'
import { filtersFromSearchParams, filtersToSearchParams, hasActiveFilters } from './useCardSearchParams'

const noFilters: CardSearchFilters = { q: '', game: '', set: '', rarity: '', page: 1 }

describe('filtersFromSearchParams', () => {
  it('defaults to no filter and the first page', () => {
    expect(filtersFromSearchParams(new URLSearchParams())).toEqual(noFilters)
  })

  it('reads every filter from the URL', () => {
    const params = new URLSearchParams('q=fox&game=demo&set=AAA&rarity=rare&page=3')

    expect(filtersFromSearchParams(params)).toEqual({ q: 'fox', game: 'demo', set: 'AAA', rarity: 'rare', page: 3 })
  })

  it.each(['abc', '0', '-2', '1.5', ''])('falls back to the first page for page=%j', (page) => {
    expect(filtersFromSearchParams(new URLSearchParams({ page })).page).toBe(1)
  })

  it('ignores parameters it does not know', () => {
    expect(filtersFromSearchParams(new URLSearchParams('utm_source=mail&sort=name'))).toEqual(noFilters)
  })
})

describe('filtersToSearchParams', () => {
  it('writes nothing for the default state, keeping the URL clean', () => {
    expect(filtersToSearchParams(noFilters).toString()).toBe('')
  })

  it('writes only what differs from the default state', () => {
    expect(filtersToSearchParams({ ...noFilters, game: 'demo', page: 2 }).toString()).toBe('game=demo&page=2')
  })

  it('survives a round trip through the URL, special characters included', () => {
    const filters: CardSearchFilters = { q: 'feu & glace = ?', game: 'demo', set: 'AAA', rarity: 'ultra rare', page: 4 }

    const url = filtersToSearchParams(filters).toString()

    expect(filtersFromSearchParams(new URLSearchParams(url))).toEqual(filters)
  })
})

describe('hasActiveFilters', () => {
  it('does not count the page number as a filter', () => {
    expect(hasActiveFilters({ ...noFilters, page: 5 })).toBe(false)
  })

  it.each(['q', 'game', 'set', 'rarity'] as const)('counts %s as a filter', (key) => {
    expect(hasActiveFilters({ ...noFilters, [key]: 'x' })).toBe(true)
  })
})
