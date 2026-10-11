type ProgressTrackProps = {
  /** From 0 to 100. */
  percent: number
  /** Height and place in the layout: `h-2 w-full`, `h-1.5 flex-1`. */
  className?: string
}

/**
 * The track of a progress bar and what is filled of it. A picture only: the
 * caller says the value, in words or with a `progressbar` role.
 */
export function ProgressTrack({ percent, className = '' }: ProgressTrackProps) {
  return (
    <div
      aria-hidden="true"
      className={`rounded-full bg-sunken transition-shadow duration-500 motion-reduce:transition-none ${
        percent >= 100 ? 'shadow-[0_0_10px_var(--progress-end)]' : ''
      } ${className}`}
    >
      {/* The gradient spans the whole track and is uncovered as the rate
          grows: a colour stands for a place on the way, not for a share
          of what is filled. */}
      <div
        style={{ clipPath: `inset(0 ${100 - percent}% 0 0 round 9999px)` }}
        className="h-full rounded-full bg-linear-to-r from-progress-start via-progress-middle to-progress-end transition-[clip-path] duration-500 ease-out motion-reduce:transition-none"
      />
    </div>
  )
}
