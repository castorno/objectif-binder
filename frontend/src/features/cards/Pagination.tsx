import { Link } from 'react-router'
import { pageItems } from '../../lib/pagination'

type PaginationProps = {
  page: number
  totalPages: number
  /** Builds the URL of a given page, keeping the current filters. */
  hrefForPage: (page: number) => string
}

const itemClasses = 'inline-flex h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm font-medium'

export function Pagination({ page, totalPages, hrefForPage }: PaginationProps) {
  if (totalPages <= 1) return null

  return (
    <nav aria-label="Pagination des résultats" className="mt-8 flex justify-center">
      <ul className="flex flex-wrap items-center gap-1">
        <li>
          <StepLink to={page > 1 ? hrefForPage(page - 1) : null} label="Précédent" />
        </li>
        {pageItems(page, totalPages).map((item, index) =>
          item === 'gap' ? (
            <li key={`gap-${index}`} aria-hidden="true" className="hidden px-1 text-muted sm:block">
              …
            </li>
          ) : (
            <li key={item} className="hidden sm:block">
              <Link
                to={hrefForPage(item)}
                aria-label={`Page ${item}`}
                aria-current={item === page ? 'page' : undefined}
                className={`${itemClasses} ${item === page ? 'bg-accent text-on-accent' : 'hover:bg-sunken'}`}
              >
                {item}
              </Link>
            </li>
          ),
        )}
        <li className="px-2 text-sm text-muted sm:hidden">
          Page {page} sur {totalPages}
        </li>
        <li>
          <StepLink to={page < totalPages ? hrefForPage(page + 1) : null} label="Suivant" />
        </li>
      </ul>
    </nav>
  )
}

function StepLink({ to, label }: { to: string | null; label: string }) {
  if (to === null) {
    return (
      <span aria-disabled="true" className={`${itemClasses} text-muted opacity-60`}>
        {label}
      </span>
    )
  }

  return (
    <Link to={to} className={`${itemClasses} border border-line bg-surface hover:bg-sunken`}>
      {label}
    </Link>
  )
}
