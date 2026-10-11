const countFormatter = new Intl.NumberFormat('fr-FR')

const blockClasses = 'mt-6 rounded-xl border border-line bg-surface px-5 py-3'

/** Slides to a new value, unless the user asked for less motion. */
function slideClasses(property: 'clip-path' | 'left,transform' | 'all'): string {
  const transition = { 'clip-path': 'transition-[clip-path]', 'left,transform': 'transition-[left,transform]', all: 'transition-all' }

  return `${transition[property]} duration-500 ease-out motion-reduce:transition-none`
}

/** The colour of the track's gradient at a given place: same stops, mixed in the same colour space. */
function gradientColorAt(percent: number): string {
  return percent < 50
    ? `color-mix(in oklab, var(--progress-start), var(--progress-middle) ${percent * 2}%)`
    : `color-mix(in oklab, var(--progress-middle), var(--progress-end) ${(percent - 50) * 2}%)`
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
  const isComplete = owned === total
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
        <div
          className={`h-2 w-full rounded-full bg-sunken transition-shadow duration-500 motion-reduce:transition-none ${
            isComplete ? 'shadow-[0_0_10px_var(--progress-end)]' : ''
          }`}
        >
          {/* The gradient spans the whole track and is uncovered as the rate
              grows: a colour stands for a place on the way, not for a share
              of what is filled. */}
          <div
            style={{ clipPath: `inset(0 ${100 - percent}% 0 0 round 9999px)` }}
            className={`h-full rounded-full bg-linear-to-r from-progress-start via-progress-middle to-progress-end ${slideClasses('clip-path')}`}
          />
        </div>
        {/* A small card slides along the track. Shifting it back by its own
            share of its width keeps it inside the track at both ends. */}
        <span
          style={{ left: `${percent}%`, transform: `translateX(-${percent}%)` }}
          className={`absolute top-0.5 block h-7 w-5 ${slideClasses('left,transform')}`}
        >
          {/* The colour of the gradient where the card stands. Once everything
              is owned it stands upright and glows. */}
          <span
            style={{ backgroundColor: gradientColorAt(percent) }}
            className={`block h-full w-full rounded-[3px] ring-2 ring-surface ${slideClasses('all')} ${
              isComplete ? 'scale-110 shadow-[0_0_12px_var(--progress-end)]' : 'rotate-6 shadow-card'
            }`}
          >
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
