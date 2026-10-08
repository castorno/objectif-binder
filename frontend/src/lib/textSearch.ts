/**
 * Text as a search compares it: lower case, without accents. "Écarlate" and
 * "ecarlate" are the same word to someone typing on a keyboard.
 */
export function normalizeForSearch(text: string): string {
  return text
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()
}

/**
 * Whether every word of the query is somewhere in the text, in any order
 * and at any position: "dragon" finds "EX Dragon", "ex dragon" does not
 * find "Majesté des Dragons". An empty query matches everything.
 */
export function matchesSearch(text: string, query: string): boolean {
  const haystack = normalizeForSearch(text)

  return normalizeForSearch(query)
    .split(/\s+/)
    .filter((word) => word !== '')
    .every((word) => haystack.includes(word))
}
