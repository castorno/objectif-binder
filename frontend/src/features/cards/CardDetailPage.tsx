import { useQuery } from '@tanstack/react-query'
import { Link, useLocation, useParams } from 'react-router'
import { ApiError } from '../../api/client'
import { cardDetailQuery } from '../../api/queries'
import { buttonStyles } from '../../components/buttonStyles'
import { StateMessage } from '../../components/StateMessage'
import { pageTitle } from '../../config'
import { formatLongMonth } from '../../lib/dates'
import { OwnedCardPanel } from '../collection/OwnedCardPanel'
import { identityCardsPath } from '../identities/identityLinks'
import { CardArt } from './CardArt'
import { setCardsPath } from './useCardSearchParams'
import { PriceEstimate } from './PriceEstimate'
import { PullOdds } from './PullOdds'
import { RarityBadge } from './RarityBadge'

function formatAttributeValue(value: unknown): string {
  if (Array.isArray(value)) return value.map(formatAttributeValue).join(', ')
  if (typeof value === 'object' && value !== null) return JSON.stringify(value)

  return String(value)
}

export function CardDetailPage() {
  const { id = '' } = useParams()
  const location = useLocation()
  const card = useQuery(cardDetailQuery(id))

  const fromSearch: unknown = location.state?.fromSearch
  const backHref = typeof fromSearch === 'string' ? `/${fromSearch}` : '/'
  const backLink = (
    <Link to={backHref} className="inline-block rounded-md text-sm font-medium text-accent underline-offset-4 hover:underline">
      <span aria-hidden="true">← </span>
      Retour au catalogue
    </Link>
  )

  if (card.isPending) {
    return (
      <>
        {backLink}
        <p role="status" className="sr-only">
          Chargement de la carte…
        </p>
        <div aria-hidden="true" className="mt-6 grid gap-8 motion-safe:animate-pulse md:grid-cols-[minmax(0,20rem)_1fr]">
          <div className="mx-auto aspect-[5/7] w-full max-w-xs rounded-[6%/4.3%] bg-sunken" />
          <div className="space-y-4">
            <div className="h-9 w-2/3 rounded bg-sunken" />
            <div className="h-4 w-1/3 rounded bg-sunken" />
            <div className="h-28 rounded-xl bg-sunken" />
          </div>
        </div>
      </>
    )
  }

  if (card.isError) {
    const notFound = card.error instanceof ApiError && card.error.status === 404

    return (
      <>
        <title>{pageTitle(notFound ? 'Carte introuvable' : 'Erreur')}</title>
        <StateMessage
          tone={notFound ? 'neutral' : 'danger'}
          title={notFound ? 'Carte introuvable' : 'Impossible de charger la carte'}
          action={
            notFound ? (
              <Link to={backHref} className={buttonStyles.primary}>
                Retour au catalogue
              </Link>
            ) : (
              <button type="button" onClick={() => void card.refetch()} className={buttonStyles.secondary}>
                Réessayer
              </button>
            )
          }
        >
          {notFound
            ? 'Cette carte n\'existe pas ou a été retirée du catalogue.'
            : 'Le serveur n\'a pas répondu correctement. Vérifiez que l\'API est démarrée, puis réessayez.'}
        </StateMessage>
      </>
    )
  }

  const { data } = card
  const attributes = Object.entries(data.attributes)
  const releaseMonth = data.setReleaseDate === null ? '' : formatLongMonth(data.setReleaseDate)

  return (
    <>
      <title>{pageTitle(data.name)}</title>
      {backLink}

      <article className="mt-6 grid gap-8 md:grid-cols-[minmax(0,20rem)_1fr]">
        <div className="mx-auto flex w-full max-w-xs flex-col gap-4">
          <CardArt
            name={data.name}
            setCode={data.setCode}
            numberInSet={data.numberInSet}
            imageUrl={data.largeImageUrl ?? data.imageUrl}
            className="w-full text-xl"
          />
          <PriceEstimate cardId={data.id} />
        </div>

        <div className="flex flex-col gap-6">
          <header className="flex flex-col items-start gap-2">
            <h1 className="text-3xl font-semibold tracking-tight">{data.name}</h1>
            <p className="text-muted">
              <Link
                to={setCardsPath(data.gameSlug, data.setCode)}
                className="rounded-md font-medium text-accent underline-offset-4 hover:underline"
              >
                {data.setName}
              </Link>{' '}
              ({data.setCode}) · n° {data.numberInSet}
            </p>
            {releaseMonth !== '' && <p className="text-sm text-muted">Extension sortie en {releaseMonth}</p>}
            <RarityBadge rarity={data.rarity} />
            {data.identities.length > 0 && (
              <ul className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                {data.identities.map((identity) => (
                  <li key={identity.id}>
                    <Link
                      to={identityCardsPath(identity.id, data.gameSlug)}
                      className="rounded-md font-medium text-accent underline-offset-4 hover:underline"
                    >
                      Toutes les cartes « {identity.name} »
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </header>

          <PullOdds oneIn={data.pullOddsOneIn} source={data.pullOddsSource} setName={data.setName} />

          <OwnedCardPanel cardId={data.id} />

          {attributes.length > 0 && (
            <section aria-labelledby="attributes-title">
              <h2 id="attributes-title" className="text-sm font-medium text-muted">
                Caractéristiques
              </h2>
              <dl className="mt-2 divide-y divide-line rounded-xl border border-line bg-surface">
                {attributes.map(([key, value]) => (
                  <div key={key} className="flex justify-between gap-4 px-5 py-3 text-sm">
                    <dt className="text-muted first-letter:uppercase">{key}</dt>
                    <dd className="text-right font-medium">{formatAttributeValue(value)}</dd>
                  </div>
                ))}
              </dl>
            </section>
          )}
        </div>
      </article>
    </>
  )
}
