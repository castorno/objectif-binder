import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { identitySearchQuery, ownedIdentitiesQuery } from '../../api/queries'
import { WITHOUT_IDENTITY, type CardSearchFilters } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { StateMessage } from '../../components/StateMessage'
import { useSession } from '../auth/useSession'
import { CardGridSkeleton, gridClasses } from '../cards/CardGrid'
import { CompletionRate, CompletionRateSkeleton } from '../collection/CompletionRate'
import { Pagination } from '../cards/Pagination'
import { filtersToSearchParams } from '../cards/useCardSearchParams'
import { identityCardsPath } from './identityLinks'
import { IdentityTile } from './IdentityTile'

const countFormatter = new Intl.NumberFormat('fr-FR')

function startedDetail(started: number, total: number): string {
  return `${countFormatter.format(started)} avec au moins une carte · ${countFormatter.format(total - started)} sans aucune carte`
}

type IdentityResultsProps = {
  filters: CardSearchFilters
  onReset: () => void
  onPageSizeChange: (pageSize: number) => void
}

/** The results of the grouped catalogue: one entry per identity instead of one per card. */
export function IdentityResults({ filters, onReset, onPageSizeChange }: IdentityResultsProps) {
  const { user } = useSession()
  const identities = useQuery(identitySearchQuery(filters))
  // What the signed-in user owns of each entry. An extra: if it cannot be
  // loaded, the entries are simply shown without it.
  const owned = useQuery({ ...ownedIdentitiesQuery(filters), enabled: user !== null })
  const ownedCounts = user !== null && owned.isSuccess && !owned.isPlaceholderData ? owned.data : undefined

  const isSearching = filters.q !== ''
  const meta = identities.data?.meta
  const showsCompletion = user !== null && owned.isSuccess && owned.data.totalIdentities > 0
  // The progress block shows the number of results, from its skeleton on so
  // that nothing jumps; the status line then only speaks.
  const countsInBlock = user !== null && (owned.isPending || showsCompletion)
  const count = meta === undefined ? undefined : `${countFormatter.format(meta.total)} ${meta.total > 1 ? 'groupes' : 'groupe'}`
  const isPageOutOfRange = identities.isSuccess && identities.data.data.length === 0 && identities.data.meta.total > 0
  // The cards no entry leads to close the list: on its last page, and not in
  // the results of a search by name, which they do not match.
  const showsOtherCards =
    meta !== undefined && !isSearching && meta.cardsWithoutIdentity > 0 && meta.page >= meta.totalPages
  const isEmpty = identities.isSuccess && identities.data.meta.total === 0 && !showsOtherCards

  return (
    <>
      {user !== null && owned.isPending && <CompletionRateSkeleton />}
      {showsCompletion && (
        // An entry counts as soon as one of its cards is owned: the progress
        // through the entries themselves, next to the one inside each.
        <CompletionRate
          owned={owned.data.startedIdentities}
          total={owned.data.totalIdentities}
          dimmed={owned.isPlaceholderData}
          detail={startedDetail(owned.data.startedIdentities, owned.data.totalIdentities)}
          count={count}
        />
      )}

      <section aria-labelledby="results-title" aria-busy={identities.isFetching} className="mt-4">
        <h2 id="results-title" className="sr-only">
          Résultats
        </h2>
        <p role="status" className={countsInBlock ? 'sr-only' : 'mb-3 min-h-5 text-sm text-muted'}>
          {count}
        </p>

        {identities.isPending && <CardGridSkeleton />}

        {identities.isError && (
          <StateMessage
            tone="danger"
            title="Impossible de charger les regroupements"
            action={
              <button type="button" onClick={() => void identities.refetch()} className={buttonStyles.secondary}>
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

        {isEmpty && (
          <StateMessage
            title={isSearching ? 'Aucun regroupement ne correspond à cette recherche' : 'Aucun regroupement de cartes ici'}
            action={
              (isSearching || filters.game !== '') && (
                <button type="button" onClick={onReset} className={buttonStyles.secondary}>
                  Réinitialiser les filtres
                </button>
              )
            }
          >
            {isSearching ? 'Essayez un autre nom.' : 'Les cartes de ce catalogue ne sont pas regroupées.'}
          </StateMessage>
        )}

        {identities.isSuccess && (identities.data.data.length > 0 || showsOtherCards) && !isPageOutOfRange && (
          <>
            <ul className={`${gridClasses} transition-opacity ${identities.isPlaceholderData ? 'opacity-50' : ''}`}>
              {identities.data.data.map((identity) => (
                <IdentityTile
                  key={identity.id}
                  name={identity.name}
                  sortOrder={identity.sortOrder}
                  cardCount={identity.cardCount}
                  // A card the user owns rather than the one everyone gets.
                  imageUrl={ownedCounts?.ownedImageByIdentity[identity.id] ?? identity.imageUrl}
                  owned={ownedCounts === undefined ? undefined : (ownedCounts.ownedByIdentity[identity.id] ?? 0)}
                  to={identityCardsPath(identity.id, identity.gameSlug)}
                />
              ))}
              {showsOtherCards && (
                <IdentityTile
                  name="Autres cartes"
                  cardCount={identities.data.meta.cardsWithoutIdentity}
                  owned={ownedCounts?.ownedWithoutIdentity}
                  to={identityCardsPath(WITHOUT_IDENTITY, filters.game)}
                />
              )}
            </ul>
            <Pagination
              page={identities.data.meta.page}
              totalPages={identities.data.meta.totalPages}
              hrefForPage={(page) => `?${filtersToSearchParams({ ...filters, page })}`}
              total={identities.data.meta.total}
              pageSize={filters.limit}
              onPageSizeChange={onPageSizeChange}
            />
          </>
        )}
      </section>
    </>
  )
}
