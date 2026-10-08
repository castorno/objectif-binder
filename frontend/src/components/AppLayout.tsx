import { Link, Outlet, ScrollRestoration, useLocation } from 'react-router'
import { AccountMenu } from '../features/auth/AccountMenu'
import { useSession } from '../features/auth/useSession'
import { NotificationsProvider } from './Notifications'
import { OWNED_CARDS_PATH } from '../features/cards/useCardSearchParams'

/** Links to the pages of the signed-in user; nothing for a visitor. */
function MainNavigation() {
  const { user } = useSession()
  const location = useLocation()

  if (user === null) return null

  // The collection is the catalogue narrowed to what the user owns.
  const isCurrent = location.pathname === '/' && new URLSearchParams(location.search).get('ownership') === 'owned'

  return (
    <nav aria-label="Navigation principale">
      <Link
        to={OWNED_CARDS_PATH}
        aria-current={isCurrent ? 'page' : undefined}
        className="rounded-md text-sm font-medium underline-offset-4 hover:underline aria-[current=page]:text-accent"
      >
        Ma collection
      </Link>
    </nav>
  )
}

export function AppLayout() {
  return (
    <NotificationsProvider>
    <div className="flex min-h-screen flex-col">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-10 focus:rounded-md focus:bg-accent focus:px-3 focus:py-2 focus:text-on-accent"
      >
        Aller au contenu
      </a>

      <header className="border-b border-line bg-surface">
        {/* Wraps onto a second line on a narrow screen once signed in. */}
        <div className="mx-auto flex min-h-14 max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2">
          <Link to="/" className="flex items-center gap-2 rounded-md font-display text-lg font-semibold tracking-tight">
            <span aria-hidden="true" className="relative block h-6 w-5">
              <span className="absolute inset-0 -rotate-12 rounded-[3px] bg-accent-soft ring-1 ring-accent" />
              <span className="absolute inset-0 rotate-6 rounded-[3px] bg-accent" />
            </span>
            <span>
              Objectif <span className="text-accent">Binder</span>
            </span>
          </Link>
          <div className="flex min-w-0 flex-wrap items-center justify-end gap-x-5 gap-y-2">
            <MainNavigation />
            <AccountMenu />
          </div>
        </div>
      </header>

      <main id="main" className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <Outlet />
      </main>

      <footer className="border-t border-line py-4 text-center text-sm text-muted">
        Projet personnel — les données affichées sont fictives.
      </footer>

      <ScrollRestoration />
    </div>
    </NotificationsProvider>
  )
}
