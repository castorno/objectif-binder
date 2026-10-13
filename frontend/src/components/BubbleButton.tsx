import { useId, type ReactNode } from 'react'

/**
 * A button that says more in a bubble, on hover or focus. A button so that
 * it can be reached with the keyboard and tapped; the bubble is its
 * description, read out with it by a screen reader.
 */
export function BubbleButton({
  label,
  lines,
  tone,
  placement = 'above',
  wide = false,
  className,
  children,
}: {
  /** The name of the button, when what it shows does not say it. */
  label?: string
  lines: string[]
  tone: 'neutral' | 'danger'
  /** Below when there is no room above, as under the top of a page. */
  placement?: 'above' | 'below'
  /** For a few sentences rather than a few words. */
  wide?: boolean
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
        className={`invisible absolute z-10 flex w-max flex-col gap-1 ${wide ? 'max-w-80' : 'max-w-60'} ${
          placement === 'below' ? 'top-full mt-1' : tone === 'danger' ? 'bottom-full mb-4' : 'bottom-full mb-1'
        } rounded-md px-3 py-2 text-xs font-medium opacity-0 shadow-card transition-opacity group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100 motion-reduce:transition-none ${
          tone === 'danger'
            ? 'right-0 border border-danger/30 bg-danger-soft text-danger'
            : 'pointer-events-none left-0 bg-ink text-canvas'
        }`}
      >
        {lines.map((line) => (
          <span key={line}>{line}</span>
        ))}
      </span>
    </span>
  )
}
