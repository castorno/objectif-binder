import { useLocation } from 'react-router'

/**
 * Where to send someone once they are signed in: the page that sent them to
 * the sign-in screen, kept in the router state as `from`, or the home page.
 *
 * Only a path inside the application is accepted. "//host" and absolute URLs
 * would leave the site, which is how a crafted link turns a sign-in page into
 * a redirection to somewhere else.
 */
export function safeDestination(from: unknown): string {
  return typeof from === 'string' && from.startsWith('/') && !from.startsWith('//') && !from.startsWith('/\\')
    ? from
    : '/'
}

export function useDestination(): string {
  const state: unknown = useLocation().state

  return safeDestination(typeof state === 'object' && state !== null && 'from' in state ? state.from : undefined)
}

/** Router state to pass along when sending someone to the sign-in screen. */
export function useReturnHere(): { from: string } {
  const location = useLocation()

  return { from: location.pathname + location.search }
}
