/** The admin page of a set's pull rates, which can be linked to. */
export function pullRatesPath(gameSlug: string, setCode: string): string {
  return `/admin/pull-rates?${new URLSearchParams({ game: gameSlug, set: setCode })}`
}
