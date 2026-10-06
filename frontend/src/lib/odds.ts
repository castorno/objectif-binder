/** Chance, in percent, of a "1 in N" event happening on a single try. */
export function oddsToPercent(oneIn: number): number {
  return 100 / oneIn
}

/**
 * Number of independent tries needed to reach the given cumulative
 * probability of a "1 in N" event happening at least once:
 * 1 - (1 - 1/N)^n >= target  =>  n >= ln(1 - target) / ln(1 - 1/N)
 */
export function triesForProbability(oneIn: number, target = 0.5): number {
  if (oneIn <= 1) return 1

  return Math.ceil(Math.log(1 - target) / Math.log(1 - 1 / oneIn))
}
