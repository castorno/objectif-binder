import { Link, Outlet, ScrollRestoration } from 'react-router'

export function AppLayout() {
  return (
    <div className="flex min-h-screen flex-col">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-10 focus:rounded-md focus:bg-accent focus:px-3 focus:py-2 focus:text-on-accent"
      >
        Aller au contenu
      </a>

      <header className="border-b border-line bg-surface">
        <div className="mx-auto flex h-14 max-w-6xl items-center px-4">
          <Link to="/" className="flex items-center gap-2 rounded-md text-lg font-semibold tracking-tight">
            <span aria-hidden="true" className="relative block h-6 w-5">
              <span className="absolute inset-0 -rotate-12 rounded-[3px] bg-accent-soft ring-1 ring-accent" />
              <span className="absolute inset-0 rotate-6 rounded-[3px] bg-accent" />
            </span>
            <span>
              Objectif <span className="text-accent">Binder</span>
            </span>
          </Link>
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
  )
}
