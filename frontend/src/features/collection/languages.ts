/**
 * Languages offered when adding a card to the collection. The API accepts any
 * ISO 639-1 code: extending this list is all it takes to offer another one.
 */
export const LANGUAGES = [
  { code: 'fr', label: 'Français' },
  { code: 'en', label: 'Anglais' },
  { code: 'ja', label: 'Japonais' },
  { code: 'de', label: 'Allemand' },
  { code: 'es', label: 'Espagnol' },
  { code: 'it', label: 'Italien' },
  { code: 'pt', label: 'Portugais' },
  { code: 'ko', label: 'Coréen' },
  { code: 'zh', label: 'Chinois' },
] as const

/** Falls back to the code itself for a language this list does not know. */
export function languageLabel(code: string): string {
  return LANGUAGES.find((language) => language.code === code)?.label ?? code.toUpperCase()
}

/** The language the catalogue is written in: a card missing from it is named in another one. */
export const CATALOGUE_LANGUAGE = 'fr'

/**
 * The languages the catalogue can be narrowed to: those its sources tell
 * apart. Narrowing to another one would only keep the cards whose languages
 * are unknown.
 */
export const FILTER_LANGUAGES = LANGUAGES.filter(({ code }) => code === 'fr' || code === 'en')

/**
 * The languages to offer for a copy of a card, those the card was printed
 * in first: the first one is the one a new copy most likely has. The others
 * stay on offer, since a source may not know every printing.
 */
export function languagesToOffer(printedIn: string[] | null): (typeof LANGUAGES)[number][] {
  const printed = LANGUAGES.filter(({ code }) => printedIn?.includes(code))

  return [...printed, ...LANGUAGES.filter((language) => !printed.includes(language))]
}
