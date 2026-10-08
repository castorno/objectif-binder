import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { NotifyContext, type Notification } from './useNotify'

type ShownNotification = Notification & { id: number }

/** Long enough to read two short lines; the message can also be closed by hand. */
const DISPLAY_MILLISECONDS = 8000

/** Older messages make room: a burst of them must not cover the page. */
const MAX_SHOWN = 3

/**
 * Holds the messages and shows them at the bottom of the screen. They are
 * never needed to use the page: what they say is also visible in it.
 */
export function NotificationsProvider({ children }: { children: ReactNode }) {
  const [shown, setShown] = useState<ShownNotification[]>([])
  const nextId = useRef(1)
  const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>())

  const dismiss = useCallback((id: number) => {
    clearTimeout(timers.current.get(id))
    timers.current.delete(id)
    setShown((current) => current.filter((notification) => notification.id !== id))
  }, [])

  const notify = useCallback(
    (notification: Notification) => {
      const id = nextId.current++

      setShown((current) => [...current, { ...notification, id }].slice(-MAX_SHOWN))
      timers.current.set(
        id,
        setTimeout(() => dismiss(id), DISPLAY_MILLISECONDS),
      )
    },
    [dismiss],
  )

  // Leaving the page with messages still shown: their timers go with them.
  useEffect(() => {
    const pending = timers.current

    return () => pending.forEach((timer) => clearTimeout(timer))
  }, [])

  const value = useMemo(() => notify, [notify])

  return (
    <NotifyContext value={value}>
      {children}
      {/* Always in the page, even empty: a screen reader only announces what
          is added to a region it already knows. A live region rather than
          role="status", which pages use for their own state. */}
      <div
        aria-live="polite"
        className="pointer-events-none fixed inset-x-0 bottom-0 z-30 flex flex-col items-center gap-2 p-4 sm:items-end"
      >
        {shown.map((notification) => (
          <div
            key={notification.id}
            className="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-accent bg-surface p-4 shadow-card transition-opacity duration-300 motion-reduce:transition-none starting:opacity-0"
          >
            <span aria-hidden="true" className="mt-0.5 block h-5 w-4 shrink-0 rotate-6 rounded-[3px] bg-accent" />
            <div className="min-w-0 flex-1">
              <p className="leading-snug font-medium">{notification.title}</p>
              {notification.detail !== undefined && <p className="mt-0.5 text-sm text-muted">{notification.detail}</p>}
            </div>
            <button
              type="button"
              aria-label="Fermer le message"
              onClick={() => dismiss(notification.id)}
              className="-mt-1 -mr-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted hover:bg-sunken hover:text-ink"
            >
              <svg aria-hidden="true" viewBox="0 0 16 16" className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <path d="M3 3l10 10M13 3L3 13" />
              </svg>
            </button>
          </div>
        ))}
      </div>
    </NotifyContext>
  )
}
