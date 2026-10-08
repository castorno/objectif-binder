import { useQuery } from '@tanstack/react-query'
import { Link, useLocation, useParams } from 'react-router'
import { ApiError } from '../../api/client'
import { cardDetailQuery } from '../../api/queries'
import { buttonStyles } from '../../components/buttonStyles'
import { StateMessage } from '../../components/StateMessage'
import { pageTitle } from '../../config'
import { OwnedCardPanel } from '../collection/OwnedCardPanel'
import { CardArt } from './CardArt'
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

  // Back to the list the card was opened from, with its filters; the
  // catalogue when the page was opened directly.
  const fromSearch: unknown = location.state?.fromSearch
  const fromCollection = location.state?.fromPath === '/collection'
  const backHref = `${fromCollection ? '/collection' : '/'}${typeof fromSearch === 'string' ? fromSearch : ''}`
  const backLabel = fromCollection ? 'Retour à ma collection' : 'Retour au catalogue'
  const backLink = (
    <Link to={backHref} className="inline-block rounded-md text-sm font-medium text-accent underline-offset-4 hover:underline">
      <span aria-hidden="true">← </span>
      {backLabel}
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
                {backLabel}
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

  return (
    <>
      <title>{pageTitle(data.name)}</title>
      {backLink}

      <article className="mt-6 grid gap-8 md:grid-cols-[minmax(0,20rem)_1fr]">
        <CardArt
          name={data.name}
          setCode={data.setCode}
          numberInSet={data.numberInSet}
          className="mx-auto w-full max-w-xs text-xl"
        />

        <div className="flex flex-col gap-6">
          <header className="flex flex-col items-start gap-2">
            <h1 className="text-3xl font-semibold tracking-tight">{data.name}</h1>
            <p className="text-muted">
              {data.setName} ({data.setCode}) · n° {data.numberInSet}
            </p>
            <RarityBadge rarity={data.rarity} />
          </header>

          <PullOdds oneIn={data.pullOddsOneIn} setName={data.setName} />

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
