import { act, renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import { MemoryRouter, useLocation, useNavigate } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { CardSearchFilters } from '../../api/types'
import {
  filtersFromSearchParams,
  filtersToSearchParams,
  hasActiveFilters,
  useCardSearchParams,
} from './useCardSearchParams'

const noFilters: CardSearchFilters = {
  q: '',
  game: '',
  set: '',
  rarity: '',
  ownership: '',
  identity: '',
  view: '',
  page: 1,
}

/**
 * Renders the hook inside a router whose history holds `entries`, starting on
 * the last one. `location` and `navigate` are exposed next to it to read the
 * URL it writes and to move through the history like the browser would.
 */
function renderSearchHook(...entries: string[]) {
  return renderHook(
    () => ({ search: useCardSearchParams(), location: useLocation(), navigate: useNavigate() }),
    {
      wrapper: ({ children }: { children: ReactNode }) => (
        <MemoryRouter initialEntries={entries}>{children}</MemoryRouter>
      ),
    },
  )
}

function wait(ms: number) {
  act(() => {
    vi.advanceTimersByTime(ms)
  })
}

describe('useCardSearchParams', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('updateFilters', () => {
    it('writes the change to the URL and goes back to the first page', () => {
      const { result } = renderSearchHook('/?q=fox&page=3')

      act(() => result.current.search.updateFilters({ rarity: 'rare' }))

      expect(result.current.location.search).toBe('?q=fox&rarity=rare')
      expect(result.current.search.filters).toEqual({ ...noFilters, q: 'fox', rarity: 'rare' })
    })

    it('drops the set and the rarity when the game changes', () => {
      const { result } = renderSearchHook('/?game=demo&set=AAA&rarity=rare&page=2')

      act(() => result.current.search.updateFilters({ game: 'other' }))

      expect(result.current.location.search).toBe('?game=other')
    })

    it('replaces the history entry instead of adding one', () => {
      const { result } = renderSearchHook('/cards/42', '/')

      act(() => result.current.search.updateFilters({ game: 'demo' }))
      act(() => void result.current.navigate(-1))

      expect(result.current.location.pathname).toBe('/cards/42')
    })
  })

  describe('queryDraft', () => {
    it('starts from the query in the URL', () => {
      const { result } = renderSearchHook('/?q=fox')

      expect(result.current.search.queryDraft).toBe('fox')
    })

    it('updates at once but only reaches the URL after the debounce delay', () => {
      const { result } = renderSearchHook('/')

      act(() => result.current.search.setQueryDraft('fox'))
      wait(299)

      expect(result.current.search.queryDraft).toBe('fox')
      expect(result.current.location.search).toBe('')

      wait(1)

      expect(result.current.location.search).toBe('?q=fox')
    })

    it('restarts the delay on every keystroke, sending only the final text', () => {
      const { result } = renderSearchHook('/')

      act(() => result.current.search.setQueryDraft('f'))
      wait(200)
      act(() => result.current.search.setQueryDraft('fo'))
      wait(200)

      expect(result.current.location.search).toBe('')

      wait(100)

      expect(result.current.location.search).toBe('?q=fo')
    })

    it('goes back to the first page once the typed text is applied', () => {
      const { result } = renderSearchHook('/?game=demo&page=4')

      act(() => result.current.search.setQueryDraft('fox'))
      wait(300)

      expect(result.current.location.search).toBe('?q=fox&game=demo')
    })

    it('follows the URL when it changes from outside the input', () => {
      const { result } = renderSearchHook('/?q=fox', '/?q=owl')

      act(() => void result.current.navigate(-1))

      expect(result.current.search.queryDraft).toBe('fox')
    })
  })

  describe('resetFilters', () => {
    it('clears every filter', () => {
      const { result } = renderSearchHook('/?q=fox&game=demo&set=AAA&rarity=rare&page=2')

      act(() => result.current.search.resetFilters())

      expect(result.current.location.search).toBe('')
      expect(result.current.search.queryDraft).toBe('')
    })

    it('discards text typed but not applied yet, so it does not come back', () => {
      const { result } = renderSearchHook('/?game=demo')

      act(() => result.current.search.setQueryDraft('fox'))
      wait(100)
      act(() => result.current.search.resetFilters())
      wait(1000)

      expect(result.current.search.queryDraft).toBe('')
      expect(result.current.location.search).toBe('')
    })
  })
})

describe('filtersFromSearchParams', () => {
  it('defaults to no filter and the first page', () => {
    expect(filtersFromSearchParams(new URLSearchParams())).toEqual(noFilters)
  })

  it('reads every filter from the URL', () => {
    const params = new URLSearchParams('q=fox&game=demo&set=AAA&rarity=rare&page=3')

    expect(filtersFromSearchParams(params)).toEqual({ ...noFilters, q: 'fox', game: 'demo', set: 'AAA', rarity: 'rare', page: 3 })
  })

  it.each(['abc', '0', '-2', '1.5', ''])('falls back to the first page for page=%j', (page) => {
    expect(filtersFromSearchParams(new URLSearchParams({ page })).page).toBe(1)
  })

  it('ignores parameters it does not know', () => {
    expect(filtersFromSearchParams(new URLSearchParams('utm_source=mail&sort=name'))).toEqual(noFilters)
  })

  it.each(['owned', 'missing'] as const)('reads the ownership "%s"', (ownership) => {
    expect(filtersFromSearchParams(new URLSearchParams({ ownership })).ownership).toBe(ownership)
  })

  it.each(['11111111-1111-4111-8111-111111111111', 'none'])('reads the identity "%s"', (identity) => {
    expect(filtersFromSearchParams(new URLSearchParams({ identity })).identity).toBe(identity)
  })

  it('drops an identity the API would refuse', () => {
    expect(filtersFromSearchParams(new URLSearchParams('identity=pikachu'))).toEqual(noFilters)
  })

  it('reads the grouped view, and anything else as the list of cards', () => {
    expect(filtersFromSearchParams(new URLSearchParams('view=identities')).view).toBe('identities')
    expect(filtersFromSearchParams(new URLSearchParams('view=table'))).toEqual(noFilters)
  })

  it('reads an unknown ownership as every card', () => {
    expect(filtersFromSearchParams(new URLSearchParams('ownership=stolen'))).toEqual(noFilters)
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
    const filters: CardSearchFilters = {
      q: 'feu & glace = ?',
      game: 'demo',
      set: 'AAA',
      rarity: 'ultra rare',
      ownership: 'missing',
      identity: '11111111-1111-4111-8111-111111111111',
      view: 'identities',
      page: 4,
    }

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

  it('counts the identity as a filter, but not the view', () => {
    expect(hasActiveFilters({ ...noFilters, identity: 'none' })).toBe(true)
    expect(hasActiveFilters({ ...noFilters, view: 'identities' })).toBe(false)
  })

  it('counts the ownership as a filter', () => {
    expect(hasActiveFilters({ ...noFilters, ownership: 'missing' })).toBe(true)
  })
})
