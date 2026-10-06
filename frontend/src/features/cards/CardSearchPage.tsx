import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { cardSearchQuery } from '../../api/queries'
import { buttonStyles } from '../../components/buttonStyles'
import { StateMessage } from '../../components/StateMessage'
import { CardFilters } from './CardFilters'
import { CardGrid, CardGridSkeleton } from './CardGrid'
import { Pagination } from './Pagination'
import { filtersToSearchParams, hasActiveFilters, useCardSearchParams } from './useCardSearchParams'

const countFormatter = new Intl.NumberFormat('fr-FR')

export function CardSearchPage() {
  const { filters, queryDraft, setQueryDraft, updateFilters, resetFilters } = useCardSearchParams()
  const search = useQuery(cardSearchQuery(filters))

  const isFiltered = hasActiveFilters(filters)
  const total = search.data?.meta.total
  // Reachable through a hand-edited or outdated URL (?page=99).
  const isPageOutOfRange = search.isSuccess && search.data.data.length === 0 && search.data.meta.total > 0

  return (
    <>
      <title>Catalogue — tcgCollector</title>

      <h1 className="text-3xl font-semibold tracking-tight">Catalogue</h1>
      <p className="mt-1 text-muted">Recherchez une carte par nom, jeu, extension ou rareté.</p>

      <div className="mt-6">
        <CardFilters
          filters={filters}
          queryDraft={queryDraft}
          onQueryDraftChange={setQueryDraft}
          onChange={updateFilters}
          onReset={resetFilters}
        />
      </div>

      <section aria-labelledby="results-title" aria-busy={search.isFetching} className="mt-6">
        <h2 id="results-title" className="sr-only">
          Résultats
        </h2>
        <p role="status" className="mb-3 min-h-5 text-sm text-muted">
          {total !== undefined && `${countFormatter.format(total)} ${total > 1 ? 'cartes' : 'carte'}`}
        </p>

        {search.isPending && <CardGridSkeleton />}

        {search.isError && (
          <StateMessage
            tone="danger"
            title="Impossible de charger les cartes"
            action={
              <button type="button" onClick={() => void search.refetch()} className={buttonStyles.secondary}>
                Réessayer
              </button>
            }
          >
            Le serveur n'a pas répondu correctement. Vérifiez que l'API est démarrée, puis réessayez.
          </StateMessage>
        )}

        {isPageOutOfRange && (
          <StateMessage
            title="Cette page de résultats n'existe pas"
            action={
              <Link to={`?${filtersToSearchParams({ ...filters, page: 1 })}`} className={buttonStyles.secondary}>
                Revenir à la première page
              </Link>
            }
          />
        )}

        {search.isSuccess && total === 0 && (
          <StateMessage
            title={isFiltered ? 'Aucune carte ne correspond à cette recherche' : 'Le catalogue est vide'}
            action={
              isFiltered && (
                <button type="button" onClick={resetFilters} className={buttonStyles.secondary}>
                  Réinitialiser les filtres
                </button>
              )
            }
          >
            {isFiltered
              ? 'Essayez un autre nom ou retirez un filtre.'
              : 'Aucune carte n\'a encore été importée.'}
          </StateMessage>
        )}

        {search.isSuccess && search.data.data.length > 0 && (
          <>
            <CardGrid cards={search.data.data} dimmed={search.isPlaceholderData} />
            <Pagination
              page={search.data.meta.page}
              totalPages={search.data.meta.totalPages}
              hrefForPage={(page) => `?${filtersToSearchParams({ ...filters, page })}`}
            />
          </>
        )}
      </section>
    </>
  )
}
