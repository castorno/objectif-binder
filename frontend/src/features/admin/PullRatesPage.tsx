import { useQuery } from '@tanstack/react-query'
import { useId, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { gameSetsQuery, gamesQuery, setPullRatesQuery } from '../../api/queries'
import type { CardSet, PullRate, SetPullRates } from '../../api/types'
import { BubbleButton } from '../../components/BubbleButton'
import { buttonStyles } from '../../components/buttonStyles'
import { ComboboxField } from '../../components/ComboboxField'
import { SelectField } from '../../components/SelectField'
import { SetLogo } from '../../components/SetLogo'
import { StateMessage } from '../../components/StateMessage'
import { TextField } from '../../components/TextField'
import { useNotify } from '../../components/useNotify'
import { pageTitle } from '../../config'
import { formatDay, formatLongMonth } from '../../lib/dates'
import { formatEuros, parseEuros } from '../../lib/money'
import { setOptions } from '../cards/setOptions'
import { useSaveBoosterPrice } from './useSaveBoosterPrice'
import { useSaveForcedRarity } from './useSaveForcedRarity'
import { useSavePullRates } from './useSavePullRates'
import { useSaveSetImage } from './useSaveSetImage'
import { useSaveSetParent } from './useSaveSetParent'

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

/** A setting of the set other than its rates, in a panel of its own. */
const panelClasses = 'flex flex-col gap-2 rounded-xl border border-line bg-surface p-5'

function pullRatesPath(gameSlug: string, setCode: string): string {
  return `/admin/pull-rates?${new URLSearchParams({ game: gameSlug, set: setCode })}`
}

/**
 * Says which set the cards of this one come in the boosters of. Saved as
 * soon as it is picked: it is one answer, not a form.
 */
function ParentSetField({ pullRates, sets, gameSlug }: { pullRates: SetPullRates; sets: CardSet[]; gameSlug: string }) {
  const notify = useNotify()
  const save = useSaveSetParent(pullRates.set.id, (parentId) => {
    const parent = sets.find((set) => set.id === parentId)
    notify({
      title: parent === undefined ? 'Extension détachée' : 'Extension liée',
      detail: parent === undefined ? pullRates.set.name : `${pullRates.set.name} → ${parent.name}`,
    })
  })
  const hintId = useId()
  const hasSubSets = pullRates.subSets.length > 0
  // One level only: a parent is a set that comes with no other.
  const candidates = sets.filter((set) => set.parentCode === null && set.code !== pullRates.set.code)

  return (
    <section aria-labelledby="parent-set-title" className={panelClasses}>
      <h3 id="parent-set-title" className="text-sm font-medium text-muted">
        Extension liée
      </h3>
      {hasSubSets ? (
        <p className="text-sm">
          Comprend {pullRates.subSets.length > 1 ? 'les sous-extensions' : 'la sous-extension'} :{' '}
          {pullRates.subSets.map((subSet, index) => (
            <span key={subSet.id}>
              {index > 0 && ', '}
              <Link
                to={pullRatesPath(gameSlug, subSet.code)}
                className="rounded-md font-medium text-accent underline-offset-4 hover:underline"
              >
                {subSet.name}
              </Link>
            </span>
          ))}
          . {pullRates.subSets.length > 1 ? 'Leurs' : 'Ses'} cartes sont comptées dans le tableau ci-dessus.
        </p>
      ) : (
        <>
          <ComboboxField
            label="Ses cartes sortent des boosters de"
            allLabel="Ses propres boosters"
            emptyMessage="Aucune extension ne correspond."
            value={pullRates.parent?.code ?? ''}
            disabled={save.isPending}
            describedBy={hintId}
            onChange={(code) => save.mutate(sets.find((set) => set.code === code)?.id ?? null)}
            options={setOptions(candidates)}
          />
          <p id={hintId} className="text-sm text-muted">
            Pour une galerie ou une collection classique sortie dans les boosters d'une autre extension : ses
            cartes apparaîtront avec celles de l'extension principale.
          </p>
        </>
      )}
      {save.isError && (
        <p role="alert" className="text-sm text-danger">
          Le lien n'a pas pu être enregistré. Réessayez.
        </p>
      )}
    </section>
  )
}

/**
 * Names the rarity all the cards of a sub-set get: they come out of the
 * boosters of the main set at a rate of their own, which needs a rarity of
 * their own to be entered.
 */
function ForcedRarityField({ pullRates }: { pullRates: SetPullRates }) {
  const notify = useNotify()
  const save = useSaveForcedRarity(pullRates.set.id, (saved) =>
    notify(
      saved === null
        ? { title: 'Raretés rendues à la source', detail: "Elles reviendront au prochain import de l'extension." }
        : { title: 'Rareté appliquée', detail: `${pullRates.set.name} : ${saved}` },
    ),
  )
  const [name, setName] = useState(pullRates.forcedRarity ?? '')
  const changed = name.trim() !== (pullRates.forcedRarity ?? '')

  function onSubmit(event: FormEvent) {
    event.preventDefault()
    const typed = name.trim()

    save.mutate(typed === '' ? null : typed)
  }

  return (
    <form onSubmit={onSubmit} className={panelClasses}>
      <TextField
        label="Rareté de ses cartes"
        value={name}
        maxLength={100}
        placeholder="Celles de la source"
        onChange={(event) => setName(event.target.value)}
        hint="Par exemple « Reprint » ou « Galerie de Dresseurs ». Toutes ses cartes prennent cette rareté, qui a alors sa propre ligne dans les taux de l'extension principale. Laissez vide pour garder les raretés de la source."
      />
      {save.isError && (
        <p role="alert" className="text-sm text-danger">
          La rareté n'a pas pu être appliquée. Réessayez.
        </p>
      )}
      <div>
        <button
          type="submit"
          disabled={!changed || save.isPending}
          className={`${buttonStyles.secondary} disabled:cursor-not-allowed disabled:opacity-50`}
        >
          {save.isPending ? 'Application…' : 'Appliquer'}
        </button>
      </div>
    </form>
  )
}

/** Same limit as the API. */
const MAX_IMAGE_BYTES = 2 * 1024 * 1024

/**
 * The picture that stands for the set: its imported logo, unless an
 * administrator sends another. Sent as soon as it is chosen.
 */
function SetImageField({ pullRates }: { pullRates: SetPullRates }) {
  const notify = useNotify()
  const inputId = useId()
  const hintId = useId()
  const [tooLarge, setTooLarge] = useState(false)
  const save = useSaveSetImage(pullRates.set.id, (sent) =>
    notify({ title: sent ? 'Image enregistrée' : 'Image retirée', detail: pullRates.set.name }),
  )

  return (
    <section aria-labelledby="set-image-title" className={panelClasses}>
      <h3 id="set-image-title" className="text-sm font-medium text-muted">
        Image de l'extension
      </h3>
      {pullRates.imageUrl === null ? (
        <p className="text-sm text-muted">Aucune image : le nom de l'extension est affiché seul.</p>
      ) : (
        <div className="flex h-20 items-center rounded-lg border border-line bg-sunken px-4">
          <SetLogo url={pullRates.imageUrl} className="max-h-14 max-w-full" />
        </div>
      )}
      <div className="flex flex-wrap items-center gap-3">
        <label htmlFor={inputId} className={`${buttonStyles.secondary} cursor-pointer has-[:disabled]:opacity-50`}>
          {save.isPending ? 'Envoi…' : pullRates.hasCustomImage ? "Remplacer l'image" : 'Envoyer une image'}
          <input
            id={inputId}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            aria-describedby={hintId}
            disabled={save.isPending}
            className="sr-only"
            onChange={(event) => {
              const file = event.target.files?.[0]
              // The same file can be chosen again after a failure.
              event.target.value = ''
              if (file === undefined) return

              setTooLarge(file.size > MAX_IMAGE_BYTES)
              if (file.size <= MAX_IMAGE_BYTES) save.mutate(file)
            }}
          />
        </label>
        {pullRates.hasCustomImage && (
          <button
            type="button"
            disabled={save.isPending}
            onClick={() => save.mutate(null)}
            className="rounded-md px-1 py-2 text-sm font-medium text-danger underline-offset-4 hover:underline disabled:opacity-50"
          >
            Retirer l'image
          </button>
        )}
      </div>
      <p id={hintId} className="text-sm text-muted">
        PNG, JPEG ou WebP, 2 Mo au plus. Elle remplace le logo importé avec l'extension, qui revient si vous la retirez.
      </p>
      {(tooLarge || save.isError) && (
        <p role="alert" className="text-sm text-danger">
          {tooLarge
            ? 'Cette image dépasse 2 Mo.'
            : "L'image n'a pas pu être enregistrée. Vérifiez que c'est bien une image PNG, JPEG ou WebP."}
        </p>
      )}
    </section>
  )
}

/**
 * What a booster of the set costs. Entered by hand: no source gives it, and
 * it is what lets the boosters be compared for what they bring per euro.
 */
function BoosterPriceField({ pullRates }: { pullRates: SetPullRates }) {
  const notify = useNotify()
  const save = useSaveBoosterPrice(pullRates.set.id, (saved) =>
    notify(
      saved === null
        ? { title: 'Prix retiré', detail: pullRates.set.name }
        : { title: 'Prix enregistré', detail: `${pullRates.set.name} : ${formatEuros(saved)} le booster` },
    ),
  )
  const savedText = pullRates.boosterPriceCents === null ? '' : (pullRates.boosterPriceCents / 100).toFixed(2).replace('.', ',')
  const [price, setPrice] = useState(savedText)

  const typed = price.trim()
  const cents = typed === '' ? null : parseEuros(typed)
  const isInvalid = typed !== '' && (cents === null || cents === 0)
  const changed = cents !== pullRates.boosterPriceCents

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        if (!isInvalid) save.mutate(cents)
      }}
      noValidate
      className={panelClasses}
    >
      <TextField
        label="Prix d'un booster (€)"
        value={price}
        inputMode="decimal"
        placeholder="Non renseigné"
        onChange={(event) => setPrice(event.target.value)}
        error={isInvalid ? 'Un prix en euros, par exemple 5,99.' : undefined}
        hint="Sert à comparer les boosters par euro dépensé. Aucune source ne le donne : indiquez le prix que vous payez."
      />
      {save.isError && (
        <p role="alert" className="text-sm text-danger">
          Le prix n'a pas pu être enregistré. Réessayez.
        </p>
      )}
      <div>
        <button
          type="submit"
          disabled={!changed || isInvalid || save.isPending}
          className={`${buttonStyles.secondary} disabled:cursor-not-allowed disabled:opacity-50`}
        >
          {save.isPending ? 'Enregistrement…' : 'Enregistrer le prix'}
        </button>
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
          options={setOptions(sets.data ?? [])}
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
              <div className="mt-3 grid items-start gap-4 md:grid-cols-2">
                {pullRates.data.parent === null && (
                  <BoosterPriceField
                    key={`${pullRates.data.set.id} ${pullRates.data.boosterPriceCents}`}
                    pullRates={pullRates.data}
                  />
                )}
                {pullRates.data.parent === null && <SetImageField key={pullRates.data.set.id} pullRates={pullRates.data} />}
                <ParentSetField
                  key={pullRates.data.set.id}
                  pullRates={pullRates.data}
                  sets={sets.data ?? []}
                  gameSlug={gameSlug}
                />
                {pullRates.data.parent !== null && (
                  <ForcedRarityField key={`${pullRates.data.set.id} ${pullRates.data.forcedRarity}`} pullRates={pullRates.data} />
                )}
              </div>
            </section>
          </div>
        )}
      </div>
    </>
  )
}
