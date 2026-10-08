import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { collectionSearchQuery } from '../../api/queries'
import type { OwnedCard } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { StateMessage } from '../../components/StateMessage'
import { pageTitle } from '../../config'
import { CardFilters } from '../cards/CardFilters'
import { CardGrid, CardGridSkeleton } from '../cards/CardGrid'
import { Pagination } from '../cards/Pagination'
import { filtersToSearchParams, hasActiveFilters, useCardSearchParams } from '../cards/useCardSearchParams'
import { languageLabel } from './languages'

const countFormatter = new Intl.NumberFormat('fr-FR')

/** What is owned of a card, e.g. "FR ×2 · JA ×1". */
function OwnedSummary({ owned }: { owned: OwnedCard[] }) {
  return (
    <p className="mt-1 flex flex-wrap gap-1">
      {owned.map((entry) => (
        <span key={entry.language} className="rounded-md bg-accent-soft px-1.5 py-0.5 text-xs font-semibold tabular-nums">
          <span aria-hidden="true">
            {entry.language.toUpperCase()} ×{entry.quantity}
          </span>
          <span className="sr-only">
            {languageLabel(entry.language)} : {entry.quantity} {entry.quantity > 1 ? 'exemplaires' : 'exemplaire'}
          </span>
        </span>
      ))}
    </p>
  )
}

/** Rendered under RequireAuth, so there is always a signed-in user here. */
export function CollectionPage() {
  const search = useCardSearchParams()
  const { queryDraft, setQueryDraft, updateFilters, resetFilters } = search
  // Everything here is owned: the ownership filter of the catalogue means nothing.
  const filters = { ...search.filters, ownership: '' as const }
  const collection = useQuery(collectionSearchQuery(filters))

  const isFiltered = hasActiveFilters(filters)
  const total = collection.data?.meta.total
  // Reachable through a hand-edited or outdated URL (?page=99), or after
  // removing the last card of the last page.
  const isPageOutOfRange =
    collection.isSuccess && collection.data.data.length === 0 && collection.data.meta.total > 0
  const ownedByCardId = new Map(collection.data?.data.map((entry) => [entry.card.id, entry.owned]))

  return (
    <>
      <title>{pageTitle('Ma collection')}</title>

      <h1 className="text-3xl font-semibold tracking-tight">Ma collection</h1>
      <p className="mt-1 text-muted">Les cartes que vous possédez, par langue.</p>

      <div className="mt-6">
        <CardFilters
          filters={filters}
          queryDraft={queryDraft}
          onQueryDraftChange={setQueryDraft}
          onChange={updateFilters}
          onReset={resetFilters}
        />
      </div>

      <section aria-labelledby="collection-results-title" aria-busy={collection.isFetching} className="mt-6">
        <h2 id="collection-results-title" className="sr-only">
          Cartes possédées
        </h2>
        <p role="status" className="mb-3 min-h-5 text-sm text-muted">
          {total !== undefined && `${countFormatter.format(total)} ${total > 1 ? 'cartes' : 'carte'}`}
        </p>

        {collection.isPending && <CardGridSkeleton />}

        {collection.isError && (
          <StateMessage
            tone="danger"
            title="Impossible de charger votre collection"
            action={
              <button type="button" onClick={() => void collection.refetch()} className={buttonStyles.secondary}>
                Réessayer
              </button>
            }
          >
            Le serveur n'a pas répondu correctement. Vérifiez que l'API est démarrée, puis réessayez.
          </StateMessage>
        )}

        {isPageOutOfRange && (
          <StateMessage
            title="Cette page de votre collection n'existe pas"
            action={
              <Link to={`?${filtersToSearchParams({ ...filters, page: 1 })}`} className={buttonStyles.secondary}>
                Revenir à la première page
              </Link>
            }
          />
        )}

        {collection.isSuccess && total === 0 && (
          <StateMessage
            title={isFiltered ? 'Aucune carte de votre collection ne correspond' : 'Votre collection est vide'}
            action={
              isFiltered ? (
                <button type="button" onClick={resetFilters} className={buttonStyles.secondary}>
                  Réinitialiser les filtres
                </button>
              ) : (
                <Link to="/" className={buttonStyles.primary}>
                  Parcourir le catalogue
                </Link>
              )
            }
          >
            {isFiltered
              ? 'Essayez un autre nom ou retirez un filtre.'
              : 'Ouvrez une carte du catalogue pour l\'ajouter à votre collection.'}
          </StateMessage>
        )}

        {collection.isSuccess && collection.data.data.length > 0 && (
          <>
            <CardGrid
              cards={collection.data.data.map((entry) => entry.card)}
              dimmed={collection.isPlaceholderData}
              footerFor={(card) => <OwnedSummary owned={ownedByCardId.get(card.id) ?? []} />}
            />
            <Pagination
              page={collection.data.meta.page}
              totalPages={collection.data.meta.totalPages}
              hrefForPage={(page) => `?${filtersToSearchParams({ ...filters, page })}`}
            />
          </>
        )}
      </section>
    </>
  )
}
