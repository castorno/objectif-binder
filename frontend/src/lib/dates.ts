// Dates come from the API as "YYYY-MM-DD", which JavaScript reads as UTC:
// formatted in the visitor's time zone, the 1st of a month could show as
// the month before.
const shortMonth = new Intl.DateTimeFormat('fr-FR', { month: 'short', year: 'numeric', timeZone: 'UTC' })
const longMonth = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' })

function parse(isoDate: string): Date | null {
  const date = new Date(`${isoDate}T00:00:00Z`)

  return Number.isNaN(date.getTime()) ? null : date
}

/** "nov. 2003": a release date where space is short. Empty for a date that cannot be read. */
export function formatShortMonth(isoDate: string): string {
  const date = parse(isoDate)

  return date === null ? '' : shortMonth.format(date)
}

/** "novembre 2003". Empty for a date that cannot be read. */
export function formatLongMonth(isoDate: string): string {
  const date = parse(isoDate)

  return date === null ? '' : longMonth.format(date)
}

const fullDate = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })

/** "8 octobre 2026", from a full timestamp. Empty for a date that cannot be read. */
export function formatDay(isoTimestamp: string): string {
  const date = new Date(isoTimestamp)

  return Number.isNaN(date.getTime()) ? '' : fullDate.format(date)
}
