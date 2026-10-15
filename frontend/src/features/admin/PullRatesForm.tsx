import { useId, useState, type FormEvent } from 'react'
import type { PullRate, SetPullRates } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { FormError } from '../../components/FormError'
import { StateMessage } from '../../components/StateMessage'
import { TextField } from '../../components/TextField'
import { useNotify } from '../../components/useNotify'
import { formatDay } from '../../lib/dates'
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
export function PullRatesForm({ pullRates }: { pullRates: SetPullRates }) {
  const notify = useNotify()
  const save = useSavePullRates(pullRates.set.id, () => notify({ title: 'Taux enregistrés', detail: pullRates.set.name }))
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

    save.mutate({ rates, source: source.trim() === '' ? null : source.trim() })
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
        <FormError id={errorId}>
          Les deux cases attendent un nombre entier : de 1 à 1 000 cartes, pour 1 à 1 000 000 boosters. Laissez-les
          vides pour ne pas donner de taux.
        </FormError>
      )}

      <TextField
        label="Source des chiffres"
        value={source}
        maxLength={255}
        onChange={(event) => setSource(event.target.value)}
        hint="Affichée sur la fiche des cartes. Les éditeurs publient rarement leurs taux : dites d'où vient l'estimation."
      />

      {save.isError && (
        <FormError>
          Les taux n'ont pas pu être enregistrés. Réessayez.
        </FormError>
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
