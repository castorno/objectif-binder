import { describe, expect, it } from 'vitest'
import { safeDestination } from './destination'

describe('safeDestination', () => {
  it('keeps a path inside the application, with its query string', () => {
    expect(safeDestination('/cards/42')).toBe('/cards/42')
    expect(safeDestination('/?q=renard&page=2')).toBe('/?q=renard&page=2')
  })

  it('falls back to the home page when there is nothing to go back to', () => {
    expect(safeDestination(undefined)).toBe('/')
    expect(safeDestination(null)).toBe('/')
    expect(safeDestination('')).toBe('/')
  })

  it.each(['https://evil.example', '//evil.example', '/\\evil.example', 'javascript:alert(1)', 'evil.example/login'])(
    'refuses %s, which would leave the site',
    (from) => {
      expect(safeDestination(from)).toBe('/')
    },
  )

  it('refuses anything that is not text', () => {
    expect(safeDestination({ pathname: '/cards/42' })).toBe('/')
    expect(safeDestination(42)).toBe('/')
  })
})
