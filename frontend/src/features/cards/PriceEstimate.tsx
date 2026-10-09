import { useQuery } from '@tanstack/react-query'
import { useId, type ReactNode } from 'react'
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
 * A button that says more in a bubble, on hover or focus. A button so that
 * it can be reached with the keyboard and tapped; the bubble is its
 * description, read out with it by a screen reader.
 */
function BubbleButton({
  label,
  lines,
  tone,
  className,
  children,
}: {
  /** The name of the button, when what it shows does not say it. */
  label?: string
  lines: string[]
  tone: 'neutral' | 'danger'
  className: string
  children: ReactNode
}) {
  const bubbleId = useId()

  return (
    <span className="group relative flex">
      <button
        type="button"
        aria-label={label}
        aria-describedby={bubbleId}
        // Escape puts the bubble away without moving the pointer.
        onKeyDown={(event) => {
          if (event.key === 'Escape') event.currentTarget.blur()
        }}
        className={`cursor-help rounded-md ${className}`}
      >
        {children}
      </button>
      {/* A warning stays up while the pointer is on it, so that it can be
          read at leisure. The detail of an amount lets the pointer through:
          it opens over the warning sign and the other amount, and must not
          keep them out of reach. */}
      <span
        id={bubbleId}
        role="tooltip"
        className={`invisible absolute bottom-full z-10 flex w-max max-w-60 flex-col gap-1 rounded-md px-3 py-2 text-xs font-medium opacity-0 shadow-card transition-opacity group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100 motion-reduce:transition-none ${
          tone === 'danger'
            ? 'right-0 mb-4 border border-danger/30 bg-danger-soft text-danger'
            : 'pointer-events-none left-0 mb-1 bg-ink text-canvas'
        }`}
      >
        {lines.map((line) => (
          <span key={line}>{line}</span>
        ))}
      </span>
    </span>
  )
}

/**
 * One version of the card: a single amount, and what it is made of in a
 * bubble. The monthly average comes first: of the figures the marketplace
 * gives, it is the one a single sale moves the least.
 */
function PriceLine({ label, figures, currency }: { label?: string; figures: Figures; currency: string }) {
  const main = figures.average30Days ?? figures.trend ?? figures.low
  if (main === null) return null

  const details = [
    figures.average30Days !== null
      ? 'Moyenne des ventes sur 30 jours'
      : figures.trend !== null
        ? 'Tendance des ventes récentes'
        : 'Annonce la moins chère',
    figures.average30Days !== null && figures.trend !== null ? `Tendance : ${formatAmount(figures.trend, currency)}` : null,
    main !== figures.low && figures.low !== null ? `À partir de ${formatAmount(figures.low, currency)}` : null,
  ].filter((part) => part !== null)

  return (
    <p className="flex flex-wrap items-baseline gap-x-2">
      {label !== undefined && <span className="text-xs font-medium">{label}</span>}
      <BubbleButton
        lines={details}
        tone="neutral"
        className="text-lg font-bold tabular-nums underline decoration-muted decoration-dotted underline-offset-4"
      >
        {formatAmount(main, currency)}
      </BubbleButton>
    </p>
  )
}

/** A warning sign that says why the price is doubtful. */
function PriceWarnings({ warnings }: { warnings: string[] }) {
  return (
    <BubbleButton label="Prix à prendre avec prudence" lines={warnings} tone="danger" className="p-0.5 text-danger">
      <svg aria-hidden="true" viewBox="0 0 20 20" className="h-5 w-5" fill="currentColor">
        <path
          fillRule="evenodd"
          clipRule="evenodd"
          d="M8.49 2.9a1.75 1.75 0 0 1 3.02 0l6.25 10.72A1.75 1.75 0 0 1 16.25 16.25H3.75a1.75 1.75 0 0 1-1.51-2.63L8.49 2.9ZM10 7a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 7Zm0 7.25a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"
        />
      </svg>
    </BubbleButton>
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

  const warnings = [
    isUnstable(normal) || isUnstable(shiny) ? 'Prix très variable sur cette carte : à prendre avec prudence.' : null,
    // Sources match cards to marketplace products, and get it wrong
    // for cards of one set that share a name.
    price.sharesNameInSet
      ? "Plusieurs cartes portent ce nom dans cette extension : ce prix peut être celui d'une autre."
      : null,
  ].filter((warning) => warning !== null)

  return (
    <section aria-labelledby="price-title" className="rounded-xl border border-line bg-surface px-4 py-3">
      <div className="flex items-center justify-between gap-2">
        <h2 id="price-title" className="text-xs font-medium text-muted">
          Prix estimé
        </h2>
        {warnings.length > 0 && <PriceWarnings warnings={warnings} />}
      </div>
      <div className="mt-1 flex flex-col gap-1.5">
        <PriceLine label={hasBoth ? 'Normale' : undefined} figures={normal} currency={currency} />
        {/* The marketplace has one set of figures for "the shiny version",
            whichever the card has: holographic, reverse, or both together. */}
        <PriceLine label={hasBoth ? 'Brillante (holo ou reverse)' : undefined} figures={shiny} currency={currency} />
      </div>
      {/* An order of magnitude, not a quote: say where it comes from and what it mixes. */}
      <p className="mt-2 text-xs text-muted">
        Carte non gradée, toutes langues et tous états confondus.
        {price.marketplace !== null && ` Source : ${price.marketplace}`}
        {price.marketplace !== null && date !== '' && `, ${date}`}
        {price.marketplace !== null && '.'}
      </p>
      {productUrl !== null && (
        <p className="mt-1 text-xs">
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
