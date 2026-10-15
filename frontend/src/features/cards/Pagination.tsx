import { useId } from 'react'
import { Link } from 'react-router'
import { PAGE_SIZES, pageItems } from '../../lib/pagination'

type PaginationProps = {
  page: number
  totalPages: number
  /** Builds the URL of a given page, keeping the current filters. */
  hrefForPage: (page: number) => string
  /** How many entries the whole search has. */
  total: number
  /** How many entries a page holds. */
  pageSize: number
  onPageSizeChange: (pageSize: number) => void
}

/** Pages shown on each side of the current one: enough to jump two pages ahead. */
const SIBLINGS = 2

const itemClasses = 'inline-flex h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm font-medium'

export function Pagination({ page, totalPages, hrefForPage, total, pageSize, onPageSizeChange }: PaginationProps) {
  const pageSizeId = useId()
  // A larger page only helps a search that does not fit in the smallest one.
  const offersPageSize = total > PAGE_SIZES[0]
  if (totalPages <= 1 && !offersPageSize) return null

  return (
    <div className="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-3">
      {totalPages > 1 && <PageLinks page={page} totalPages={totalPages} hrefForPage={hrefForPage} />}
      {offersPageSize && (
        <div className="flex items-center gap-2">
          <label htmlFor={pageSizeId} className="text-sm text-muted">
            Par page
          </label>
          <select
            id={pageSizeId}
            value={pageSize}
            onChange={(event) => onPageSizeChange(Number(event.target.value))}
            className="h-10 rounded-lg border border-line bg-surface px-3 text-sm"
          >
            {PAGE_SIZES.map((size) => (
              <option key={size} value={size}>
                {size}
              </option>
            ))}
          </select>
        </div>
      )}
    </div>
  )
}

function PageLinks({ page, totalPages, hrefForPage }: Pick<PaginationProps, 'page' | 'totalPages' | 'hrefForPage'>) {
  return (
    <nav aria-label="Pagination des résultats">
      <ul className="flex flex-wrap items-center gap-1">
        <li>
          <StepLink to={page > 1 ? hrefForPage(page - 1) : null} label="Précédent" />
        </li>
        {pageItems(page, totalPages, SIBLINGS).map((item, index) =>
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
