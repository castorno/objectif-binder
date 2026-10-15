export type PageItem = number | 'gap'

/** How many entries a page can hold; the API takes up to 100. */
export const PAGE_SIZES = [20, 40, 60, 100] as const

export const DEFAULT_PAGE_SIZE: number = PAGE_SIZES[0]

/**
 * The page that holds the first entry of `page` once pages hold `newSize`
 * entries instead of `size`: changing the size keeps the same cards in view.
 */
export function pageForSize(page: number, size: number, newSize: number): number {
  return Math.floor(((page - 1) * size) / newSize) + 1
}

/**
 * Pages to display in a pagination control: first, last, and a window around
 * the current page, with 'gap' markers where pages are skipped.
 * e.g. pageItems(6, 12) => [1, 'gap', 5, 6, 7, 'gap', 12]
 */
export function pageItems(current: number, total: number, siblings = 1): PageItem[] {
  const items: PageItem[] = []
  let previous = 0

  for (let page = 1; page <= total; page++) {
    const visible = page === 1 || page === total || Math.abs(page - current) <= siblings
    if (!visible) continue

    if (page - previous === 2) {
      // A gap hiding a single page is pointless: show the page instead.
      items.push(page - 1)
    } else if (page - previous > 2) {
      items.push('gap')
    }
    items.push(page)
    previous = page
  }

  return items
}
