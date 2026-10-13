import { CATALOGUE_LANGUAGE, languageLabel } from '../collection/languages'

/**
 * Says when a card was never printed in the language of the catalogue: its
 * name is then in another language, and it cannot be found in that one.
 * Nothing for the other cards, and nothing when the languages are unknown.
 */
export function LanguageBadge({ languages }: { languages: string[] | null }) {
  if (languages === null || languages.length === 0 || languages.includes(CATALOGUE_LANGUAGE)) return null

  return (
    <span className="inline-flex items-center rounded-full border border-line px-2 py-0.5 text-xs font-medium">
      <span aria-hidden="true">{languages.map((code) => code.toUpperCase()).join(' · ')} uniquement</span>
      <span className="sr-only">
        Sortie uniquement en {languages.map((code) => languageLabel(code).toLowerCase()).join(', ')}
      </span>
    </span>
  )
}
