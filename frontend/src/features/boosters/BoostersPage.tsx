import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router'
import { boostersQuery } from '../../api/queries'
import type { BoosterOdds } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { SetLogo } from '../../components/SetLogo'
import { StateMessage } from '../../components/StateMessage'
import { pageTitle } from '../../config'
import { formatShortMonth } from '../../lib/dates'
import { formatEuros } from '../../lib/money'
import { useSession } from '../auth/useSession'
import { missingSetCardsPath } from '../cards/useCardSearchParams'
import { identityCardsPath } from '../identities/identityLinks'

const averageFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 })
const countFormatter = new Intl.NumberFormat('fr-FR')
// Two significant digits: 15 %, 5,6 %, 0,32 %.
const chanceFormatter = new Intl.NumberFormat('fr-FR', { style: 'percent', maximumSignificantDigits: 2 })

const perEuroFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 })

/** How many new entries a euro spent on this booster brings on average; null without a price. */
function newEntriesPerEuro(booster: BoosterOdds): number | null {
  return booster.boosterPriceCents === null ? null : booster.expectedNewIdentities / (booster.boosterPriceCents / 100)
}

const linkClasses = 'rounded-md font-medium text-accent underline-offset-4 hover:underline'

function Booster({ booster }: { booster: BoosterOdds }) {
  const { set } = booster
  const titleId = `booster-${set.id}`
  const released = set.releaseDate === null ? '' : formatShortMonth(set.releaseDate)
  const perEuro = newEntriesPerEuro(booster)

  return (
    <li>
      <article aria-labelledby={titleId} className="rounded-xl border border-line bg-surface p-5">
        <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
          <div className="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
            {/* The logo of the set, when it has one; its name is written either way. */}
            <SetLogo url={set.imageUrl} className="h-10 max-w-36" />
            <h2 id={titleId} className="text-lg font-semibold">
              {set.name}
            </h2>
          </div>
          <p className="text-sm text-muted">
            {[released, booster.boosterPriceCents === null ? '' : `${formatEuros(booster.boosterPriceCents)} le booster`]
              .filter((part) => part !== '')
              .join(' · ')}
          </p>
        </header>

        {booster.missingIdentityCount === 0 ? (
          <p className="mt-3 text-sm text-muted">
            Vous avez déjà au moins une carte de chacune de ses {countFormatter.format(booster.identityCount)} entrées.
          </p>
        ) : (
          <p className="mt-3 flex flex-wrap items-baseline gap-x-2">
            <span className="text-3xl font-bold tabular-nums">{averageFormatter.format(booster.expectedNewIdentities)}</span>{' '}
            <span className="text-sm">
              {booster.expectedNewIdentities >= 2 ? 'nouvelles entrées' : 'nouvelle entrée'} par booster
            </span>
            {perEuro !== null && (
              <>
                {' '}
                <span className="text-sm text-muted">· {perEuroFormatter.format(perEuro)} par euro</span>
              </>
            )}
          </p>
        )}

        <p className="mt-1 text-sm text-muted">
          {averageFormatter.format(booster.expectedMissingCards)} {booster.expectedMissingCards >= 2 ? 'cartes' : 'carte'}{' '}
          que vous n'avez pas par booster ·{' '}
          {/* Under the name the game gives its identities ("Pokédex", "Créatures"), when it gives one. */}
          {set.identityLabel ?? 'Entrées'} : {countFormatter.format(booster.missingIdentityCount)} à découvrir sur{' '}
          {countFormatter.format(booster.identityCount)}
        </p>

        {booster.likeliest.length > 0 && (
          <p className="mt-3 text-sm">
            <span className="text-muted">Les plus probables : </span>
            {booster.likeliest.map((identity, index) => (
              <span key={identity.id}>
                {index > 0 && ' · '}
                <Link to={identityCardsPath(identity.id, set.gameSlug)} className={linkClasses}>
                  {identity.name}
                </Link>{' '}
                <span className="text-muted tabular-nums">{chanceFormatter.format(identity.chance)}</span>
              </span>
            ))}
          </p>
        )}

        {booster.incomplete && (
          <p className="mt-3 text-sm font-medium text-danger">
            Taux de drop incomplets : ces chiffres ne comptent pas toutes les raretés du booster.
          </p>
        )}

        <p className="mt-4 text-sm">
          <Link to={missingSetCardsPath(set.gameSlug, set.code)} className={linkClasses}>
            Voir les cartes qu'il vous manque
          </Link>
        </p>
      </article>
    </li>
  )
}

/**
 * Which booster to open: the sets whose pull rates are entered, with what a
 * booster of each brings on average to the signed-in user, the most
 * rewarding first. Rendered under RequireAuth.
 */
export function BoostersPage() {
  const { user } = useSession()
  const boosters = useQuery(boostersQuery())
  const isAdmin = user?.isAdmin === true

  // In the address, like the filters of the catalogue: a link keeps the order.
  const [searchParams, setSearchParams] = useSearchParams()
  const byEuro = searchParams.get('sort') === 'euro'
  const list = boosters.data?.data ?? []
  const somePriced = list.some((booster) => booster.boosterPriceCents !== null)
  // The API ranks by booster. Per euro, a booster without price cannot be
  // ranked and goes last, in the order it had.
  const ranked =
    byEuro && somePriced
      ? [...list].sort((a, b) => (newEntriesPerEuro(b) ?? -1) - (newEntriesPerEuro(a) ?? -1))
      : list

  return (
    <div className="mx-auto max-w-3xl">
      <title>{pageTitle('Quel booster ouvrir ?')}</title>
      <h1 className="text-3xl font-semibold tracking-tight">Quel booster ouvrir ?</h1>
      <p className="mt-2 text-sm text-muted">
        Ce qu'un booster de chaque extension vous apporte en moyenne, d'après votre collection et les taux de drop
        renseignés. Le plus intéressant vient en premier. Ce sont des estimations, pas des promesses.
      </p>

      <div className="mt-6">
        {boosters.isPending && (
          <p role="status" className="text-sm text-muted">
            Chargement…
          </p>
        )}
        {boosters.isError && (
          <StateMessage
            tone="danger"
            title="Impossible de charger les boosters"
            action={
              <button type="button" onClick={() => void boosters.refetch()} className={buttonStyles.secondary}>
                Réessayer
              </button>
            }
          >
            Le serveur n'a pas répondu correctement.
          </StateMessage>
        )}
        {boosters.isSuccess && boosters.data.data.length === 0 && (
          <StateMessage title="Aucune extension n'a encore ses taux de drop">
            Sans eux, rien ne dit ce qu'un booster contient.
          </StateMessage>
        )}
        {/* Nothing to choose between until a booster has a price. */}
        {somePriced && (
          <div role="group" aria-label="Classement" className="mb-4 inline-flex gap-1 rounded-lg border border-line bg-surface p-1">
            {[
              { label: 'Par booster', active: !byEuro, params: new URLSearchParams() },
              { label: 'Par euro', active: byEuro, params: new URLSearchParams({ sort: 'euro' }) },
            ].map((option) => (
              <button
                key={option.label}
                type="button"
                aria-pressed={option.active}
                onClick={() => setSearchParams(option.params, { replace: true })}
                className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                  option.active ? 'bg-accent text-on-accent' : 'hover:bg-sunken'
                }`}
              >
                {option.label}
              </button>
            ))}
          </div>
        )}
        {ranked.length > 0 && (
          <ol className="flex flex-col gap-4">
            {ranked.map((booster) => (
              <Booster key={booster.set.id} booster={booster} />
            ))}
          </ol>
        )}

        {boosters.isSuccess && (boosters.data.setsWithoutRates > 0 || isAdmin) && (
          <p className="mt-6 text-sm text-muted">
            {boosters.data.setsWithoutRates > 0 &&
              `${countFormatter.format(boosters.data.setsWithoutRates)} ${
                boosters.data.setsWithoutRates > 1 ? "extensions n'ont" : "extension n'a"
              } pas encore ${boosters.data.setsWithoutRates > 1 ? 'leurs' : 'ses'} taux de drop. `}
            {isAdmin && (
              <Link to="/admin/pull-rates" className={linkClasses}>
                Renseigner des taux
              </Link>
            )}
          </p>
        )}
      </div>
    </div>
  )
}
