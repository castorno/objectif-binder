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
