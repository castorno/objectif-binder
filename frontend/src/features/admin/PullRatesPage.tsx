import { useQuery } from '@tanstack/react-query'
import { useId, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router'
import { gameSetsQuery, gamesQuery, setPullRatesQuery } from '../../api/queries'
import type { PullRate, SetPullRates } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { ComboboxField } from '../../components/ComboboxField'
import { SelectField } from '../../components/SelectField'
import { StateMessage } from '../../components/StateMessage'
import { TextField } from '../../components/TextField'
import { useNotify } from '../../components/useNotify'
import { pageTitle } from '../../config'
import { formatDay, formatShortMonth } from '../../lib/dates'
import { useSavePullRates } from './useSavePullRates'

const oddsFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 })
const averageFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 })

/** What is typed for a rarity: so many cards for so many boosters. */
type Entry = { cards: string; boosters: string }

const EMPTY: Entry = { cards: '', boosters: '' }

function isWholeNumber(text: string, max: number): boolean {
  return /^[0-9]+$/.test(text) && Number(text) >= 1 && Number(text) <= max
}

/**
 * An entry as a rate: null when nothing is typed, 'invalid' when what is
 * typed is not one. A side left empty counts as 1: "4" cards is four per
 * booster, "8" boosters is one card every eight.
 */
function parseEntry({ cards, boosters }: Entry): PullRate | null | 'invalid' {
  const [typedCards, typedBoosters] = [cards.trim(), boosters.trim()]
  if (typedCards === '' && typedBoosters === '') return null

  if (typedCards !== '' && !isWholeNumber(typedCards, 1000)) return 'invalid'
  if (typedBoosters !== '' && !isWholeNumber(typedBoosters, 1_000_000)) return 'invalid'

  return { cards: typedCards === '' ? 1 : Number(typedCards), boosters: typedBoosters === '' ? 1 : Number(typedBoosters) }
}

function sameRate(a: PullRate | null, b: PullRate | null): boolean {
  return a === null || b === null ? a === b : a.cards === b.cards && a.boosters === b.boosters
}

/** What the card page will show: the rate shared between the cards of the rarity. */
function oddsOfOneCard(rate: PullRate, cardsInSet: number): string {
  const oneIn = (rate.boosters * cardsInSet) / rate.cards

  return oneIn <= 1 ? 'dans chaque booster' : `1 sur ${oddsFormatter.format(oneIn)}`
}

const fieldClasses = 'h-9 w-14 rounded-lg sm:w-16 border bg-surface px-2 text-right tabular-nums placeholder:text-muted'

/**
 * The rates of one set. Mounted anew for each set and after each save (see
 * the key given by the page), so its fields always start from what is saved.
 */
function PullRatesForm({ pullRates }: { pullRates: SetPullRates }) {
  const notify = useNotify()
  const save = useSavePullRates(pullRates.set.id)
  const errorId = useId()

  const [entries, setEntries] = useState<Record<string, Entry>>(() =>
    Object.fromEntries(
      pullRates.rarities.map((rarity) => [
        rarity.id,
        rarity.rate === null ? EMPTY : { cards: String(rarity.rate.cards), boosters: String(rarity.rate.boosters) },
      ]),
    ),
  )
  const [source, setSource] = useState(pullRates.source ?? '')

  const rows = pullRates.rarities.map((rarity) => ({ rarity, rate: parseEntry(entries[rarity.id] ?? EMPTY) }))
  const hasInvalid = rows.some((row) => row.rate === 'invalid')
  const changed =
    source.trim() !== (pullRates.source ?? '') ||
    rows.some((row) => row.rate === 'invalid' || !sameRate(row.rate, row.rarity.rate))
  // How many cards a booster holds, all rarities together: a quick way to
  // see whether the figures add up to the size of a booster.
  const cardsPerBooster = rows.reduce(
    (total, row) => (row.rate === null || row.rate === 'invalid' ? total : total + row.rate.cards / row.rate.boosters),
    0,
  )

  function setEntry(rarityId: string, change: Partial<Entry>) {
    setEntries((current) => ({ ...current, [rarityId]: { ...(current[rarityId] ?? EMPTY), ...change } }))
  }

  function onSubmit(event: FormEvent) {
    event.preventDefault()
    if (hasInvalid) return

    const rates: Record<string, PullRate> = {}
    for (const { rarity, rate } of rows) {
      if (rate !== null && rate !== 'invalid') rates[rarity.id] = rate
    }

    save.mutate(
      { rates, source: source.trim() === '' ? null : source.trim() },
      { onSuccess: () => notify({ title: 'Taux enregistrés', detail: pullRates.set.name }) },
    )
  }

  if (pullRates.rarities.length === 0) {
    return (
      <StateMessage title="Aucune rareté dans cette extension">
        Ses cartes n'ont pas de rareté : il n'y a pas de taux à renseigner.
      </StateMessage>
    )
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      <div className="overflow-x-auto rounded-xl border border-line bg-surface">
        <table className="w-full text-sm">
          <caption className="sr-only">Taux de drop de l'extension {pullRates.set.name}, par rareté</caption>
          <thead className="border-b border-line text-left text-xs text-muted">
            <tr>
              <th scope="col" className="px-3 py-2 font-medium sm:px-4">
                Rareté
              </th>
              <th scope="col" className="px-3 py-2 text-right font-medium sm:px-4">
                Cartes
              </th>
              <th scope="col" className="px-3 py-2 font-medium sm:px-4">
                Dans un booster
              </th>
              {/* A reading aid, left out where there is no room for it. */}
              <th scope="col" className="hidden px-3 py-2 text-right font-medium sm:table-cell sm:px-4">
                Une carte précise
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {rows.map(({ rarity, rate }) => {
              const entry = entries[rarity.id] ?? EMPTY
              const isInvalid = rate === 'invalid'

              return (
                <tr key={rarity.id}>
                  <th scope="row" className="px-3 py-2 text-left font-medium sm:px-4">
                    {rarity.name}
                  </th>
                  <td className="px-3 py-2 text-right text-muted tabular-nums sm:px-4">{rarity.cardsInSet}</td>
                  <td className="px-3 py-2 sm:px-4">
                    <div className="flex items-center gap-2 whitespace-nowrap">
                      <input
                        type="text"
                        inputMode="numeric"
                        aria-label={`${rarity.name} : nombre de cartes`}
                        value={entry.cards}
                        onChange={(event) => setEntry(rarity.id, { cards: event.target.value })}
                        aria-invalid={isInvalid ? true : undefined}
                        aria-describedby={isInvalid ? errorId : undefined}
                        // What an empty side counts as, once the other is filled.
                        placeholder={entry.boosters.trim() === '' ? '—' : '1'}
                        className={`${fieldClasses} ${isInvalid ? 'border-danger' : 'border-line'}`}
                      />
                      <span aria-hidden="true">
                        <span className="hidden sm:inline">carte(s) </span>pour
                      </span>
                      <input
                        type="text"
                        inputMode="numeric"
                        aria-label={`${rarity.name} : nombre de boosters`}
                        value={entry.boosters}
                        onChange={(event) => setEntry(rarity.id, { boosters: event.target.value })}
                        aria-invalid={isInvalid ? true : undefined}
                        aria-describedby={isInvalid ? errorId : undefined}
                        placeholder={entry.cards.trim() === '' ? '—' : '1'}
                        className={`${fieldClasses} ${isInvalid ? 'border-danger' : 'border-line'}`}
                      />
                      <span aria-hidden="true" className="hidden sm:inline">
                        booster(s)
                      </span>
                    </div>
                  </td>
                  <td className="hidden px-3 py-2 text-right whitespace-nowrap text-muted tabular-nums sm:table-cell sm:px-4">
                    {rate === null || isInvalid || rarity.cardsInSet === 0 ? '—' : oddsOfOneCard(rate, rarity.cardsInSet)}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <p className="text-sm text-muted">
        Total : {averageFormatter.format(cardsPerBooster)} {cardsPerBooster >= 2 ? 'cartes' : 'carte'} par booster, toutes
        raretés renseignées confondues.
      </p>

      {hasInvalid && (
        <p id={errorId} role="alert" className="text-sm text-danger">
          Les deux cases attendent un nombre entier : de 1 à 1 000 cartes, pour 1 à 1 000 000 boosters. Laissez-les
          vides pour ne pas donner de taux.
        </p>
      )}

      <TextField
        label="Source des chiffres"
        value={source}
        maxLength={255}
        onChange={(event) => setSource(event.target.value)}
        hint="Affichée sur la fiche des cartes. Les éditeurs publient rarement leurs taux : dites d'où vient l'estimation."
      />

      {save.isError && (
        <p role="alert" className="text-sm text-danger">
          Les taux n'ont pas pu être enregistrés. Réessayez.
        </p>
      )}

      <div className="flex flex-wrap items-center gap-4">
        <button
          type="submit"
          disabled={!changed || hasInvalid || save.isPending}
          className={`${buttonStyles.primary} disabled:cursor-not-allowed disabled:opacity-50`}
        >
          {save.isPending ? 'Enregistrement…' : 'Enregistrer'}
        </button>
        {pullRates.updatedAt !== null && (
          <p className="text-sm text-muted">Dernière modification le {formatDay(pullRates.updatedAt)}.</p>
        )}
      </div>
    </form>
  )
}

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

  return (
    <>
      <title>{pageTitle('Taux de drop')}</title>
      <h1 className="text-2xl font-semibold tracking-tight">Taux de drop</h1>
      <p className="mt-2 max-w-2xl text-sm text-muted">
        Pour chaque rareté d'une extension, combien de cartes de cette rareté un booster contient : « 4 cartes pour 1
        booster », ou « 1 carte pour 8 boosters » pour une rareté qui ne sort pas à chaque fois. La fiche d'une
        carte en déduit la chance de trouver cette carte précise.
      </p>

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
          options={(sets.data ?? []).map((candidate) => ({
            value: candidate.code,
            label: candidate.name,
            detail: [candidate.releaseDate === null ? '' : formatShortMonth(candidate.releaseDate), candidate.code]
              .filter((part) => part !== '')
              .join(' · '),
            keywords: candidate.code,
          }))}
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
          <p role="status" className="text-sm text-muted">
            Chargement…
          </p>
        )}
        {pullRates.isSuccess && (
          <PullRatesForm key={`${pullRates.data.set.id} ${pullRates.dataUpdatedAt}`} pullRates={pullRates.data} />
        )}
      </div>
    </>
  )
}
