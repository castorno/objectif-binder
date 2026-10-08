import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { collectionCompletionQuery } from '../../api/queries'
import { buttonStyles } from '../../components/buttonStyles'
import { StateMessage } from '../../components/StateMessage'
import { pageTitle } from '../../config'
import { useSession } from '../auth/useSession'
import { CompletionRate, CompletionRateSkeleton } from '../collection/CompletionRate'
import { OwnedSummary } from '../collection/OwnedSummary'
import { CardFilters } from './CardFilters'
import { CardGrid, CardGridSkeleton } from './CardGrid'
import { Pagination } from './Pagination'
import {
  filtersToSearchParams,
  hasActiveFilters,
  hasCatalogueFilters,
  useCardSearchParams,
} from './useCardSearchParams'
import { useCatalogueSearch } from './useCatalogueSearch'

const countFormatter = new Intl.NumberFormat('fr-FR')

export function CardSearchPage() {
  const { filters, queryDraft, setQueryDraft, updateFilters, resetFilters } = useCardSearchParams()
  const { search, ownership, ownedByCardId } = useCatalogueSearch(filters)
  const { user } = useSession()
  // What the signed-in user owns of this search. An extra: if it cannot be
  // loaded, the catalogue is simply shown without it.
  const completion = useQuery({ ...collectionCompletionQuery(filters), enabled: user !== null })
  // What is owned of each card on screen. The rate knows it for the page of
  // the whole search; the owned list brings its own; missing cards have none.
  const ownedOnScreen = ownership === 'owned' ? ownedByCardId : ownership === '' && user !== null ? completion.data?.ownedOnPage : undefined
  // "Ma collection" with nothing in it yet, rather than a search gone wrong.
  const isEmptyCollection = ownership === 'owned' && !hasCatalogueFilters(filters)
  const showsCompletion = user !== null && completion.isSuccess && completion.data.total > 0

  const isFiltered = hasActiveFilters(filters)
  const total = search.data?.meta.total
  // Reachable through a hand-edited or outdated URL (?page=99).
  const isPageOutOfRange = search.isSuccess && search.data.data.length === 0 && search.data.meta.total > 0

  return (
    <>
      <title>{pageTitle('Catalogue')}</title>

      <h1 className="text-3xl font-semibold tracking-tight">Catalogue</h1>
      <p className="mt-1 text-muted">Recherchez une carte par nom, jeu, extension ou rareté.</p>

      <div className="mt-6">
        <CardFilters
          filters={filters}
          queryDraft={queryDraft}
          onQueryDraftChange={setQueryDraft}
          onChange={updateFilters}
          onReset={resetFilters}
          showOwnership={user !== null}
        />
      </div>

      {user !== null && completion.isPending && <CompletionRateSkeleton />}
      {showsCompletion && (
        <CompletionRate
          owned={completion.data.owned}
          total={completion.data.total}
          // Still describing the previous search while the next one loads.
          dimmed={completion.isPlaceholderData}
        />
      )}

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
            title={
              ownership === 'missing'
                ? 'Il ne vous manque aucune carte ici'
                : isEmptyCollection
                  ? 'Votre collection est vide'
                  : isFiltered
                  ? 'Aucune carte ne correspond à cette recherche'
                  : 'Le catalogue est vide'
            }
            action={
              isFiltered && (
                <button type="button" onClick={resetFilters} className={buttonStyles.secondary}>
                  {isEmptyCollection ? 'Voir toutes les cartes' : 'Réinitialiser les filtres'}
                </button>
              )
            }
          >
            {ownership === 'missing'
              ? 'Vous possédez toutes les cartes de cette recherche.'
              : isEmptyCollection
                ? 'Ouvrez une carte du catalogue pour l\'ajouter à votre collection.'
                : isFiltered
                ? 'Essayez un autre nom ou retirez un filtre.'
                : 'Aucune carte n\'a encore été importée.'}
          </StateMessage>
        )}

        {search.isSuccess && search.data.data.length > 0 && (
          <>
            <CardGrid
              cards={search.data.data}
              dimmed={search.isPlaceholderData}
              footerFor={(card) => {
                const owned = ownedOnScreen?.[card.id] ?? []

                return owned.length > 0 && <OwnedSummary owned={owned} />
              }}
            />
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
