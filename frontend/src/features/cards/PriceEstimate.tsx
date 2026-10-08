import { useQuery } from '@tanstack/react-query'
import { cardPriceQuery } from '../../api/queries'
import type { CardPrice } from '../../api/types'
import { formatDay } from '../../lib/dates'
import { useSession } from '../auth/useSession'

function formatAmount(cents: number, currency: string): string {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(cents / 100)
}

type Figures = { trend: number | null; low: number | null; average30Days: number | null }

function hasFigures({ trend, low, average30Days }: Figures): boolean {
  return trend !== null || low !== null || average30Days !== null
}

/**
 * How far the recent price may stray from the monthly average before the
 * figures are flagged. On a card that sells rarely, one unusual sale (a
 * graded copy, a mispriced one) is enough to move the recent price a lot.
 */
const UNSTABLE_GAP = 0.2

function isUnstable({ trend, average30Days }: Figures): boolean {
  return trend !== null && average30Days !== null && Math.abs(trend - average30Days) / average30Days > UNSTABLE_GAP
}

/**
 * One version of the card. The monthly average comes first: of the figures
 * the marketplace gives, it is the one a single sale moves the least.
 */
function PriceLine({ label, figures, currency }: { label?: string; figures: Figures; currency: string }) {
  const main = figures.average30Days ?? figures.trend ?? figures.low
  if (main === null) return null

  const context = [
    figures.average30Days !== null ? 'moyenne sur 30 jours' : null,
    figures.average30Days !== null && figures.trend !== null ? `tendance ${formatAmount(figures.trend, currency)}` : null,
    main !== figures.low && figures.low !== null ? `à partir de ${formatAmount(figures.low, currency)}` : null,
  ].filter((part) => part !== null)

  return (
    <p className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
      {label !== undefined && <span className="text-sm font-medium">{label}</span>}
      <span className="font-display text-2xl font-semibold tabular-nums">{formatAmount(main, currency)}</span>
      {context.length > 0 && <span className="text-sm text-muted">{context.join(' · ')}</span>}
    </p>
  )
}

function Estimate({ price, currency }: { price: CardPrice; currency: string }) {
  const normal: Figures = { trend: price.trendCents, low: price.lowCents, average30Days: price.average30DaysCents }
  const shiny: Figures = {
    trend: price.holoTrendCents,
    low: price.holoLowCents,
    average30Days: price.holoAverage30DaysCents,
  }
  // Two versions on the market: each line says which one it is about.
  const hasBoth = hasFigures(normal) && hasFigures(shiny)
  const date = formatDay(price.sourceUpdatedAt ?? price.fetchedAt)
  // Only ever a plain web address in a link.
  const productUrl = price.productUrl?.startsWith('https://') ? price.productUrl : null

  return (
    <section aria-labelledby="price-title" className="rounded-xl border border-line bg-surface p-5">
      <h2 id="price-title" className="text-sm font-medium text-muted">
        Prix estimé
      </h2>
      <div className="mt-2 flex flex-col gap-2">
        <PriceLine label={hasBoth ? 'Normale' : undefined} figures={normal} currency={currency} />
        {/* The marketplace has one set of figures for "the shiny version",
            whichever the card has: holographic, reverse, or both together. */}
        <PriceLine label={hasBoth ? 'Brillante (holo ou reverse)' : undefined} figures={shiny} currency={currency} />
      </div>
      {(isUnstable(normal) || isUnstable(shiny)) && (
        <p className="mt-2 text-sm font-medium">Prix très variable sur cette carte : à prendre avec prudence.</p>
      )}
      {price.sharesNameInSet && (
        // Sources match cards to marketplace products, and get it wrong
        // for cards of one set that share a name.
        <p className="mt-2 text-sm font-medium">
          Plusieurs cartes portent ce nom dans cette extension : ce prix peut être celui d'une autre.
        </p>
      )}
      {/* An order of magnitude, not a quote: say where it comes from and what it mixes. */}
      <p className="mt-3 text-xs text-muted">
        Carte non gradée, toutes langues et tous états confondus.
        {price.marketplace !== null && ` Source : ${price.marketplace}`}
        {price.marketplace !== null && date !== '' && `, ${date}`}
        {price.marketplace !== null && '.'}
      </p>
      {productUrl !== null && (
        <p className="mt-2 text-sm">
          <a
            href={productUrl}
            target="_blank"
            // The other site gets no handle on this page, and is not told where the visitor comes from.
            rel="noopener noreferrer"
            className="rounded-md font-medium text-accent underline-offset-4 hover:underline"
          >
            Voir sur {price.marketplace ?? 'la place de marché'}
            <span className="sr-only"> (nouvel onglet)</span>
          </a>
        </p>
      )}
    </section>
  )
}

/**
 * What the card sells for, for a signed-in user. An extra of the page: while
 * it loads, when there is no price or when it cannot be fetched, nothing is
 * shown, and the page is complete without it.
 */
export function PriceEstimate({ cardId }: { cardId: string }) {
  const { user } = useSession()
  const price = useQuery({ ...cardPriceQuery(cardId), enabled: user !== null })

  if (user === null || !price.isSuccess || price.data === null || price.data.currency === null) return null

  return <Estimate price={price.data} currency={price.data.currency} />
}
