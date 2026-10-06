export type PageItem = number | 'gap'

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
