const euroFormatter = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })

/** "5,99 €", from an amount in cents. */
export function formatEuros(cents: number): string {
  return euroFormatter.format(cents / 100)
}

/**
 * An amount typed in euros, as cents: "5,99", "5.99" and "6" are amounts,
 * with two decimals at most. Null when the text is not one.
 */
export function parseEuros(text: string): number | null {
  const match = /^(\d{1,6})(?:[.,](\d{1,2}))?$/.exec(text.trim())
  if (match === null) return null

  return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'))
}
