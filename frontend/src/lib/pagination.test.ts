import { describe, expect, it } from 'vitest'
import { pageItems } from './pagination'

describe('pageItems', () => {
  it('keeps the first page, the last page and a window around the current one', () => {
    expect(pageItems(6, 12)).toEqual([1, 'gap', 5, 6, 7, 'gap', 12])
  })

  it('has no leading gap on the first page and no trailing gap on the last', () => {
    expect(pageItems(1, 12)).toEqual([1, 2, 'gap', 12])
    expect(pageItems(12, 12)).toEqual([1, 'gap', 11, 12])
  })

  it('shows the page itself rather than a gap hiding a single page', () => {
    expect(pageItems(4, 7)).toEqual([1, 2, 3, 4, 5, 6, 7])
  })

  it('lists every page when they all fit', () => {
    expect(pageItems(2, 3)).toEqual([1, 2, 3])
    expect(pageItems(1, 1)).toEqual([1])
  })

  it('widens the window with the number of siblings', () => {
    expect(pageItems(6, 12, 2)).toEqual([1, 'gap', 4, 5, 6, 7, 8, 'gap', 12])
  })

  it('still shows both ends when the current page is out of range', () => {
    expect(pageItems(99, 5)).toEqual([1, 'gap', 5])
  })

  it('returns nothing when there are no pages', () => {
    expect(pageItems(1, 0)).toEqual([])
  })
})
