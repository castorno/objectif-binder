const countFormatter = new Intl.NumberFormat('fr-FR')

const blockClasses = 'mt-6 rounded-xl border border-line bg-surface px-5 py-3'

/** Slides to a new value, unless the user asked for less motion. */
function slideClasses(property: 'width' | 'left,transform'): string {
  return `${property === 'width' ? 'transition-[width]' : 'transition-[left,transform]'} duration-500 ease-out motion-reduce:transition-none`
}

/**
 * Rounded down, so that "100 %" is only ever shown for a complete search, and
 * never "0 %" to someone who owns something.
 */
function formatShare(owned: number, total: number): string {
  const percent = Math.floor((owned / total) * 100)

  return owned > 0 && percent === 0 ? '< 1 %' : `${percent} %`
}

type CompletionRateProps = {
  owned: number
  /** Cards matching the search; the caller shows nothing for a search without result. */
  total: number
  dimmed?: boolean
  /** Shown on hover or focus, in place of the owned and missing counts of cards. */
  detail?: string
}

/** How much of what the catalogue lists the user owns: cards, or in the grouped view identities. */
export function CompletionRate({ owned, total, dimmed = false, detail: customDetail }: CompletionRateProps) {
  const missing = total - owned
  const percent = (owned / total) * 100
  const detail =
    customDetail ??
    `${countFormatter.format(owned)} ${owned > 1 ? 'possédées' : 'possédée'} · ${countFormatter.format(missing)} ${missing > 1 ? 'manquantes' : 'manquante'}`

  return (
    <section
      aria-labelledby="completion-title"
      className={`${blockClasses} flex items-center gap-4 transition-opacity ${dimmed ? 'opacity-50' : ''}`}
    >
      <h2 id="completion-title" className="text-sm font-medium text-muted">
        Progression
      </h2>

      {/* Focusable so that the detail shown on hover is also reachable with
          a keyboard, and with a tap on a touch screen. Tall enough for the
          card, which stands out of the track. */}
      <div
        role="progressbar"
        tabIndex={0}
        aria-labelledby="completion-title"
        aria-valuemin={0}
        aria-valuemax={total}
        aria-valuenow={owned}
        aria-valuetext={detail}
        className="group relative flex h-8 min-w-0 flex-1 items-center rounded-md"
      >
        <div className="h-2 w-full overflow-hidden rounded-full bg-sunken">
          <div style={{ width: `${percent}%` }} className={`h-full rounded-full bg-accent ${slideClasses('width')}`} />
        </div>
        {/* A small card slides along the track. Shifting it back by its own
            share of its width keeps it inside the track at both ends. */}
        <span
          style={{ left: `${percent}%`, transform: `translateX(-${percent}%)` }}
          className={`absolute top-0.5 block h-7 w-5 ${slideClasses('left,transform')}`}
        >
          <span className="block h-full w-full rotate-6 rounded-[3px] bg-accent shadow-card ring-2 ring-surface">
            <span className="absolute inset-1 rounded-[1px] bg-accent-soft/40" />
          </span>
        </span>
        {/* Screen readers already get this as the value of the bar. */}
        <span
          aria-hidden="true"
          className="pointer-events-none absolute bottom-full left-1/2 z-10 -translate-x-1/2 rounded-md bg-ink px-2 py-1 text-xs font-medium whitespace-nowrap text-canvas opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 motion-reduce:transition-none"
        >
          {detail}
        </span>
      </div>

      <p className="font-display font-semibold text-accent tabular-nums">{formatShare(owned, total)}</p>
    </section>
  )
}

/** Holds the place of the block while the rate loads, so the grid does not jump. */
export function CompletionRateSkeleton() {
  return (
    <div aria-hidden="true" className={`${blockClasses} flex h-14 items-center gap-4 motion-safe:animate-pulse`}>
      <div className="h-4 w-20 rounded bg-sunken" />
      <div className="h-2 flex-1 rounded-full bg-sunken" />
      <div className="h-4 w-10 rounded bg-sunken" />
    </div>
  )
}
