import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { gameIdentityGroupsQuery, gameSetsQuery, gamesQuery, placeholderCardsQuery, placeholderIdentitiesQuery } from '../../api/queries'
import { buttonStyles } from '../../components/buttonStyles'
import { ComboboxField } from '../../components/ComboboxField'
import { SelectField } from '../../components/SelectField'
import { StateMessage } from '../../components/StateMessage'
import { pageTitle } from '../../config'
import { setOptions } from '../cards/setOptions'

/** A standard card is 63 × 88 mm: three by three fit an A4 sheet, margins included. */
const PER_SHEET = 9

const countFormatter = new Intl.NumberFormat('fr-FR')

/** One stand-in per identity of the game, or one per card of a set. */
type Mode = 'identities' | 'cards'

/** What a stand-in shows, whatever it stands for. */
type Stand = {
  id: string
  /** The number it is looked for by in the binder. */
  number: string | null
  name: string
  /** Under the name, smaller: the rarity and the set of a card. */
  detail: string | null
  imageUrl: string | null
}

function sheetsOf(stands: Stand[]): Stand[][] {
  const sheets: Stand[][] = []
  for (let start = 0; start < stands.length; start += PER_SHEET) {
    sheets.push(stands.slice(start, start + PER_SHEET))
  }

  return sheets
}

/**
 * A stand-in the size of a card, to slip in the pocket of what the user has
 * no card of. Black on white whatever the theme: it is paper.
 */
function Placeholder({ stand, withPicture }: { stand: Stand; withPicture: boolean }) {
  const showPicture = withPicture && stand.imageUrl !== null

  return (
    // The border is the cutting line, shared with the neighbours.
    <li className="flex h-[88mm] w-[63mm] flex-col items-center justify-center gap-[3mm] overflow-hidden border-[0.2mm] border-dashed border-neutral-400 text-center">
      {showPicture && (
        <img
          src={stand.imageUrl ?? undefined}
          alt=""
          // Never lazy: a picture that was not on screen would print blank.
          loading="eager"
          referrerPolicy="no-referrer"
          // Whole, whatever its shape, and lighter than a card: a reminder
          // of what goes there, and less ink.
          className="h-[52mm] w-[52mm] object-contain opacity-70"
        />
      )}
      <p className="w-full px-[3mm]">
        {stand.number !== null && (
          <span className="block font-mono text-[5mm] leading-tight font-bold">
            <span className="sr-only">Numéro </span>
            {stand.number}
          </span>
        )}
        <span className="block text-[4mm] leading-tight font-semibold break-words">{stand.name}</span>
        {stand.detail !== null && <span className="mt-[1mm] block text-[3mm] leading-tight">{stand.detail}</span>}
      </p>
    </li>
  )
}

/**
 * Stand-ins to print, one per identity of a game or one per card of a set:
 * those the user has no card of, or all of them. The page prints itself:
 * sizes are in millimetres and the browser makes the PDF.
 */
export function PlaceholdersPage() {
  const games = useQuery(gamesQuery())
  // Only a game that groups its cards by identity has identities to stand in for.
  const gamesWithIdentities = (games.data ?? []).filter((game) => game.identityLabel !== null)

  // In the address, like the filters of the catalogue: they say what is printed.
  const [searchParams, setSearchParams] = useSearchParams()
  const mode: Mode = searchParams.get('mode') === 'cards' || (games.isSuccess && gamesWithIdentities.length === 0) ? 'cards' : 'identities'
  const offeredGames = mode === 'cards' ? (games.data ?? []) : gamesWithIdentities
  const requestedGame = searchParams.get('game') ?? ''
  const game = offeredGames.find((candidate) => candidate.slug === requestedGame) ?? offeredGames[0] ?? null
  const group = searchParams.get('group') ?? ''
  const setCode = searchParams.get('set') ?? ''
  const onlyMissing = searchParams.get('all') !== '1'

  const gameSlug = game?.slug ?? ''
  const setView = (change: { mode?: Mode; game?: string; group?: string; set?: string; onlyMissing?: boolean }) => {
    // Another game or mode starts its narrowing afresh: a group or a set belongs to one game.
    const isFresh = change.mode !== undefined || change.game !== undefined
    const next = { mode, game: gameSlug, onlyMissing, ...(isFresh ? { group: '', set: '' } : { group, set: setCode }), ...change }

    const params = new URLSearchParams()
    if (next.mode === 'cards') params.set('mode', 'cards')
    if (next.game !== '') params.set('game', next.game)
    if (next.mode === 'identities' && next.group !== '') params.set('group', next.group)
    if (next.mode === 'cards' && next.set !== '') params.set('set', next.set)
    if (!next.onlyMissing) params.set('all', '1')
    setSearchParams(params, { replace: true })
  }

  const [withPicture, setWithPicture] = useState(true)

  const groups = useQuery({ ...gameIdentityGroupsQuery(gameSlug), enabled: mode === 'identities' && game !== null })
  const sets = useQuery({ ...gameSetsQuery(gameSlug), enabled: mode === 'cards' && game !== null })
  const identities = useQuery({
    ...placeholderIdentitiesQuery(gameSlug, group, onlyMissing),
    enabled: mode === 'identities' && game !== null,
  })
  const cards = useQuery({
    ...placeholderCardsQuery(gameSlug, setCode, onlyMissing),
    // A whole game would be thousands of cards: a set at a time.
    enabled: mode === 'cards' && game !== null && setCode !== '',
  })

  const current = mode === 'identities' ? identities : cards
  const isWaiting = game !== null && (mode === 'identities' || setCode !== '')
  const stands: Stand[] =
    mode === 'identities'
      ? (identities.data ?? []).map((identity) => ({
          id: identity.id,
          number: identity.sortOrder === null ? null : String(identity.sortOrder).padStart(4, '0'),
          name: identity.name,
          detail: null,
          // What is missing, not one of its cards.
          imageUrl: identity.identityImageUrl,
        }))
      : (cards.data ?? []).map((card) => ({
          id: card.id,
          number: card.numberInSet,
          name: card.name,
          detail: [card.rarity, card.setCode].filter((part) => part !== null).join(' · '),
          imageUrl: card.imageUrl,
        }))
  const sheets = sheetsOf(stands)

  return (
    <div>
      {/* Only while this page is shown: nothing else of the site is meant for paper. */}
      <style>{'@page { size: A4; margin: 10mm; }'}</style>
      <title>{pageTitle('Cartes repères')}</title>

      <div className="print:hidden">
        <h1 className="text-3xl font-semibold tracking-tight">Cartes repères</h1>
        <p className="mt-2 max-w-2xl text-sm text-muted">
          Une carte à imprimer pour chaque entrée ou chaque carte d'une extension, à glisser dans la pochette qui
          l'attend. Par entrée, une carte qui en montre plusieurs n'en remplit aucune.
        </p>

        {games.isSuccess && game === null && (
          <div className="mt-6">
            <StateMessage title="Rien à imprimer">Le catalogue ne compte encore aucun jeu.</StateMessage>
          </div>
        )}

        {game !== null && (
          <div className="mt-6 flex flex-col gap-4">
            {gamesWithIdentities.length > 0 && (
              <fieldset className="flex flex-wrap items-center gap-x-5 gap-y-2">
                <legend className="mb-1.5 text-sm font-medium">Une carte repère par</legend>
                {(
                  [
                    ['identities', 'Entrée'],
                    ['cards', "Carte d'une extension"],
                  ] as const
                ).map(([value, label]) => (
                  <label key={value} className="flex items-center gap-2 text-sm">
                    <input
                      type="radio"
                      name="placeholder-mode"
                      value={value}
                      checked={mode === value}
                      onChange={() => setView({ mode: value })}
                      className="h-4 w-4 accent-accent"
                    />
                    {label}
                  </label>
                ))}
              </fieldset>
            )}

            <div className="flex flex-wrap items-end gap-4">
              {offeredGames.length > 1 && (
                <SelectField
                  label="Jeu"
                  value={game.slug}
                  onChange={(slug) => setView({ game: slug === '' ? game.slug : slug })}
                  options={offeredGames.map((candidate) => ({ value: candidate.slug, label: candidate.name }))}
                  allLabel="Choisir un jeu"
                />
              )}
              {mode === 'identities' && (groups.data?.length ?? 0) > 0 && (
                <SelectField
                  label={game.identityGroupLabel ?? 'Groupe'}
                  value={group}
                  onChange={(name) => setView({ group: name })}
                  options={(groups.data ?? []).map(({ name }) => ({ value: name, label: name }))}
                  allLabel="Tout"
                />
              )}
              {mode === 'cards' && (
                <div className="w-full sm:w-72">
                  <ComboboxField
                    label="Extension"
                    allLabel="Choisir une extension"
                    emptyMessage="Aucune extension ne correspond."
                    value={setCode}
                    onChange={(code) => setView({ set: code })}
                    // A sub-set prints with its main set.
                    options={setOptions((sets.data ?? []).filter((set) => set.parentCode === null || set.code === setCode))}
                  />
                </div>
              )}
              <label className="flex h-10 items-center gap-2 text-sm font-medium">
                <input
                  type="checkbox"
                  checked={onlyMissing}
                  onChange={(event) => setView({ onlyMissing: event.target.checked })}
                  className="h-4 w-4 accent-accent"
                />
                Seulement ce qui me manque
              </label>
              <label className="flex h-10 items-center gap-2 text-sm font-medium">
                <input
                  type="checkbox"
                  checked={withPicture}
                  onChange={(event) => setWithPicture(event.target.checked)}
                  className="h-4 w-4 accent-accent"
                />
                Avec l'image
              </label>
              <button
                type="button"
                onClick={() => window.print()}
                disabled={stands.length === 0}
                className={`${buttonStyles.primary} disabled:cursor-not-allowed disabled:opacity-50`}
              >
                Imprimer
              </button>
            </div>
          </div>
        )}

        {mode === 'cards' && game !== null && setCode === '' && (
          <p className="mt-6 text-sm text-muted">Choisissez une extension : ses cartes s'impriment une extension à la fois.</p>
        )}
        {isWaiting && current.isPending && (
          <p role="status" className="mt-6 text-sm text-muted">
            Chargement…
          </p>
        )}
        {current.isError && (
          <div className="mt-6">
            <StateMessage title="Impossible de charger les cartes repères" tone="danger">
              Réessayez dans un instant.
            </StateMessage>
          </div>
        )}
        {current.isSuccess && stands.length === 0 && (
          <div className="mt-6">
            <StateMessage title="Rien à imprimer">
              {!onlyMissing
                ? 'Aucune carte ne correspond.'
                : mode === 'identities'
                  ? 'Vous avez une carte de chaque entrée.'
                  : 'Vous avez toutes les cartes de cette extension.'}
            </StateMessage>
          </div>
        )}
        {stands.length > 0 && (
          <p role="status" className="mt-6 text-sm text-muted">
            {countFormatter.format(stands.length)} {stands.length > 1 ? 'cartes' : 'carte'} sur{' '}
            {countFormatter.format(sheets.length)} {sheets.length > 1 ? 'feuilles' : 'feuille'} A4. Imprimez à 100 %,
            sans « ajuster à la page » : une carte doit mesurer 63 mm de large.
          </p>
        )}
      </div>

      {/* Scrolls sideways on a narrow screen; paper gets the sheets as they are. */}
      <div className="mt-6 overflow-x-auto print:mt-0 print:overflow-visible">
        {sheets.map((sheet, index) => (
          <ul
            key={sheet[0].id}
            aria-label={`Feuille ${index + 1}`}
            className="mx-auto mb-6 grid w-[189mm] break-inside-avoid break-after-page grid-cols-3 bg-white text-black last:break-after-auto print:mb-0"
          >
            {sheet.map((stand) => (
              <Placeholder key={stand.id} stand={stand} withPicture={withPicture} />
            ))}
          </ul>
        ))}
      </div>
    </div>
  )
}
