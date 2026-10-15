import { useQuery } from '@tanstack/react-query'
import { useId } from 'react'
import { Link, useSearchParams } from 'react-router'
import { gameSetsQuery, gamesQuery, setProgressQuery, setPullRatesQuery } from '../../api/queries'
import { BubbleButton } from '../../components/BubbleButton'
import { buttonStyles } from '../../components/buttonStyles'
import { ComboboxField } from '../../components/ComboboxField'
import { SelectField } from '../../components/SelectField'
import { SetLogo } from '../../components/SetLogo'
import { StateMessage } from '../../components/StateMessage'
import { pageTitle } from '../../config'
import { formatLongMonth } from '../../lib/dates'
import { setOptions } from '../cards/setOptions'
import { BoosterPriceField } from './BoosterPriceField'
import { ForcedRarityField } from './ForcedRarityField'
import { ParentSetField } from './ParentSetField'
import { progressMark } from './progressMark'
import { PullRatesForm } from './PullRatesForm'
import { pullRatesPath } from './pullRatesPath'
import { SetImageField } from './SetImageField'

/**
 * Where an administrator enters the pull rates of a set, one figure per
 * rarity. The game and the set are in the address, so a set can be linked to.
 */
export function PullRatesPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const gameSlug = searchParams.get('game') ?? ''
  const setCode = searchParams.get('set') ?? ''
  const hintId = useId()

  const games = useQuery(gamesQuery())
  const sets = useQuery(gameSetsQuery(gameSlug))
  const set = (sets.data ?? []).find((candidate) => candidate.code === setCode)
  const pullRates = useQuery(setPullRatesQuery(set?.id ?? ''))
  // A help to find the sets left to do: if it fails, the list goes without it.
  const progress = useQuery(setProgressQuery(gameSlug))
  const progressBySetId = new Map((progress.data ?? []).map((entry) => [entry.setId, entry]))

  return (
    <>
      <title>{pageTitle('Taux de drop')}</title>
      <div className="flex items-center gap-3">
        <h1 className="text-3xl font-semibold tracking-tight">Taux de drop</h1>
        {/* What the page is for, for who asks: an administrator who comes back knows. */}
        <BubbleButton
          label="À quoi servent les taux de drop ?"
          tone="neutral"
          placement="below"
          wide
          lines={[
            "Pour chaque rareté d'une extension : combien de cartes de cette rareté un booster contient.",
            'Par exemple « 4 cartes pour 1 booster », ou « 1 carte pour 8 boosters » pour une rareté qui ne sort pas à chaque fois.',
            "La fiche d'une carte en déduit la chance de trouver cette carte précise.",
          ]}
          className="p-0.5 text-muted transition-colors hover:text-accent focus-visible:text-accent"
        >
          <svg
            aria-hidden="true"
            viewBox="0 0 20 20"
            className="h-6 w-6"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
          >
            <circle cx="10" cy="10" r="7.25" />
            <path d="M10 9.25v4.25" />
            <circle cx="10" cy="6.5" r="0.75" fill="currentColor" stroke="none" />
          </svg>
        </BubbleButton>
      </div>

      <div className="mt-6 grid gap-4 sm:max-w-2xl sm:grid-cols-2">
        <SelectField
          label="Jeu"
          allLabel="Choisir un jeu"
          value={gameSlug}
          onChange={(game) => setSearchParams(game === '' ? {} : { game })}
          options={(games.data ?? []).map((game) => ({ value: game.slug, label: game.name }))}
        />
        <ComboboxField
          label="Extension"
          allLabel="Choisir une extension"
          emptyMessage="Aucune extension ne correspond."
          value={setCode}
          disabled={gameSlug === ''}
          describedBy={gameSlug === '' ? hintId : undefined}
          onChange={(code) => setSearchParams(code === '' ? { game: gameSlug } : { game: gameSlug, set: code })}
          options={setOptions(sets.data ?? [], (candidate) => {
            const entry = progressBySetId.get(candidate.id)

            return entry === undefined ? undefined : progressMark(entry)
          })}
        />
      </div>
      {gameSlug === '' && (
        <p id={hintId} className="mt-2 text-sm text-muted">
          Choisissez un jeu pour choisir une de ses extensions.
        </p>
      )}

      <div className="mt-8 sm:max-w-3xl">
        {(games.isError || sets.isError || pullRates.isError) && (
          <StateMessage tone="danger" title="Impossible de charger les taux">
            Le serveur n'a pas répondu correctement. Rechargez la page pour réessayer.
          </StateMessage>
        )}
        {setCode !== '' && sets.isSuccess && set === undefined && (
          <StateMessage title="Extension introuvable">Aucune extension de ce jeu ne porte le code « {setCode} ».</StateMessage>
        )}
        {set !== undefined && pullRates.isPending && (
          <>
            <p role="status" className="sr-only">
              Chargement…
            </p>
            {/* Holds the place of the set, its rates and its settings, so the page does not jump. */}
            <div aria-hidden="true" className="flex flex-col gap-6 motion-safe:animate-pulse">
              <div className="h-10 w-1/2 rounded bg-sunken" />
              <div className="h-72 rounded-xl bg-sunken" />
              <div className="grid gap-4 md:grid-cols-2">
                <div className="h-40 rounded-xl bg-sunken" />
                <div className="h-40 rounded-xl bg-sunken" />
              </div>
            </div>
          </>
        )}
        {pullRates.isSuccess && set !== undefined && (
          <div className="flex flex-col gap-6">
            {/* Which set this is about, beyond the field that chose it. */}
            <header className="flex flex-wrap items-center gap-x-4 gap-y-2">
              <SetLogo url={pullRates.data.imageUrl} className="h-10 max-w-36" />
              <div>
                <h2 className="text-lg font-semibold">{set.name}</h2>
                <p className="text-sm text-muted">
                  {set.code}
                  {set.releaseDate !== null && ` · sortie en ${formatLongMonth(set.releaseDate)}`}
                </p>
              </div>
            </header>

            {/* What the page is for comes first; the other settings of the set follow. */}
            {pullRates.data.parent === null ? (
              <PullRatesForm key={`${pullRates.data.set.id} ${pullRates.dataUpdatedAt}`} pullRates={pullRates.data} />
            ) : (
              <StateMessage
                title={`Les taux se saisissent sur « ${pullRates.data.parent.name} »`}
                action={
                  <Link to={pullRatesPath(gameSlug, pullRates.data.parent.code)} className={buttonStyles.secondary}>
                    Voir ses taux
                  </Link>
                }
              >
                Les cartes de cette extension sortent des boosters de l'extension principale : un seul tableau de
                taux les couvre toutes.
              </StateMessage>
            )}

            <section aria-labelledby="set-settings-title" className="mt-2">
              <h2 id="set-settings-title" className="text-lg font-semibold">
                Réglages de l'extension
              </h2>
              {/* Each key names its field: two siblings with the same key leave a stale copy on screen. */}
              <div className="mt-3 grid items-start gap-4 md:grid-cols-2">
                {pullRates.data.parent === null && (
                  <BoosterPriceField
                    key={`price ${pullRates.data.set.id} ${pullRates.data.boosterPriceCents}`}
                    pullRates={pullRates.data}
                  />
                )}
                {pullRates.data.parent === null && <SetImageField key={`image ${pullRates.data.set.id}`} pullRates={pullRates.data} />}
                <ParentSetField
                  key={`parent ${pullRates.data.set.id}`}
                  pullRates={pullRates.data}
                  sets={sets.data ?? []}
                  gameSlug={gameSlug}
                />
                {pullRates.data.parent !== null && (
                  <ForcedRarityField key={`rarity ${pullRates.data.set.id} ${pullRates.data.forcedRarity}`} pullRates={pullRates.data} />
                )}
              </div>
            </section>
          </div>
        )}
      </div>
    </>
  )
}
