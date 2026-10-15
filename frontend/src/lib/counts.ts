const countFormatter = new Intl.NumberFormat('fr-FR')

/** "1 234": a whole number, with the French separator of thousands. */
export function formatCount(count: number): string {
  return countFormatter.format(count)
}

/** The word that goes with a count. In French, 0 and 1 take the singular. */
export function plural(count: number, singular: string, pluralForm: string): string {
  return count > 1 ? pluralForm : singular
}

/** "1 234 cartes", "1 carte", "0 carte". */
export function countLabel(count: number, singular: string, pluralForm: string): string {
  return `${formatCount(count)} ${plural(count, singular, pluralForm)}`
}
