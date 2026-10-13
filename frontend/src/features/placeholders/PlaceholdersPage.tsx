import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { gameIdentityGroupsQuery, gamesQuery, missingIdentitiesQuery } from '../../api/queries'
import type { CardIdentitySummary } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { SelectField } from '../../components/SelectField'
import { StateMessage } from '../../components/StateMessage'
import { pageTitle } from '../../config'

/** A standard card is 63 × 88 mm: three by three fit an A4 sheet, margins included. */
const PER_SHEET = 9

const countFormatter = new Intl.NumberFormat('fr-FR')

function sheetsOf(identities: CardIdentitySummary[]): CardIdentitySummary[][] {
  const sheets: CardIdentitySummary[][] = []
  for (let start = 0; start < identities.length; start += PER_SHEET) {
    sheets.push(identities.slice(start, start + PER_SHEET))
  }

  return sheets
}

/**
 * A stand-in the size of a card, to slip in the pocket of an identity the
 * user has no card of. Black on white whatever the theme: it is paper.
 */
function Placeholder({ identity, withPicture }: { identity: CardIdentitySummary; withPicture: boolean }) {
  const showPicture = withPicture && identity.imageUrl !== null

  return (
    // The border is the cutting line, shared with the neighbours.
    <li className="flex h-[88mm] w-[63mm] flex-col items-center justify-center gap-[3mm] overflow-hidden border-[0.2mm] border-dashed border-neutral-400 text-center">
      {showPicture && (
        <img
          src={identity.imageUrl ?? undefined}
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
        {identity.sortOrder !== null && (
          <span className="block font-mono text-[5mm] leading-tight font-bold">
            <span className="sr-only">Numéro </span>
            {String(identity.sortOrder).padStart(4, '0')}
          </span>
        )}
        <span className="block text-[4mm] leading-tight font-semibold break-words">{identity.name}</span>
      </p>
    </li>
  )
}

/**
 * Stand-ins to print for the identities the user has no card of. The page
 * prints itself: sizes are in millimetres and the browser makes the PDF.
 */
export function PlaceholdersPage() {
  const games = useQuery(gamesQuery())
  // Only a game that groups its cards by identity has pockets to fill.
  const gamesWithIdentities = (games.data ?? []).filter((game) => game.identityLabel !== null)

  // In the address, like the filters of the catalogue.
  const [searchParams, setSearchParams] = useSearchParams()
  const requestedGame = searchParams.get('game') ?? ''
  const game = gamesWithIdentities.find((candidate) => candidate.slug === requestedGame) ?? gamesWithIdentities[0] ?? null
  const group = searchParams.get('group') ?? ''
  const setView = (view: { game: string; group: string }) =>
    setSearchParams(new URLSearchParams(view.group === '' ? { game: view.game } : view), { replace: true })

  const [withPicture, setWithPicture] = useState(true)

  const gameSlug = game?.slug ?? ''
  const groups = useQuery({ ...gameIdentityGroupsQuery(gameSlug), enabled: game !== null })
  const missing = useQuery({ ...missingIdentitiesQuery(gameSlug, group), enabled: game !== null })
  const identities = missing.data ?? []
  const sheets = sheetsOf(identities)

  return (
    <div>
      {/* Only while this page is shown: nothing else of the site is meant for paper. */}
      <style>{'@page { size: A4; margin: 10mm; }'}</style>
      <title>{pageTitle('Cartes repères')}</title>

      <div className="print:hidden">
        <h1 className="text-3xl font-semibold tracking-tight">Cartes repères</h1>
        <p className="mt-2 max-w-2xl text-sm text-muted">
          Une carte à imprimer pour chaque entrée dont vous n'avez aucune carte, à glisser dans la pochette qui
          l'attend. Une carte qui montre plusieurs entrées n'en remplit aucune.
        </p>

        {games.isSuccess && game === null && (
          <div className="mt-6">
            <StateMessage title="Rien à imprimer">Aucun jeu du catalogue ne regroupe ses cartes par entrée.</StateMessage>
          </div>
        )}

        {game !== null && (
          <div className="mt-6 flex flex-wrap items-end gap-4">
            {gamesWithIdentities.length > 1 && (
              <SelectField
                label="Jeu"
                value={game.slug}
                onChange={(slug) => setView({ game: slug === '' ? game.slug : slug, group: '' })}
                options={gamesWithIdentities.map((candidate) => ({ value: candidate.slug, label: candidate.name }))}
                allLabel="Choisir un jeu"
              />
            )}
            {(groups.data?.length ?? 0) > 0 && (
              <SelectField
                label={game.identityGroupLabel ?? 'Groupe'}
                value={group}
                onChange={(name) => setView({ game: game.slug, group: name })}
                options={(groups.data ?? []).map(({ name }) => ({ value: name, label: name }))}
                allLabel="Tout"
              />
            )}
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
              disabled={identities.length === 0}
              className={`${buttonStyles.primary} disabled:cursor-not-allowed disabled:opacity-50`}
            >
              Imprimer
            </button>
          </div>
        )}

        {missing.isPending && game !== null && (
          <p role="status" className="mt-6 text-sm text-muted">
            Chargement…
          </p>
        )}
        {missing.isError && (
          <div className="mt-6">
            <StateMessage title="Impossible de charger les cartes repères" tone="danger">
              Réessayez dans un instant.
            </StateMessage>
          </div>
        )}
        {missing.isSuccess && identities.length === 0 && (
          <div className="mt-6">
            <StateMessage title="Rien à imprimer">Vous avez une carte de chaque entrée.</StateMessage>
          </div>
        )}
        {identities.length > 0 && (
          <p role="status" className="mt-6 text-sm text-muted">
            {countFormatter.format(identities.length)} {identities.length > 1 ? 'cartes' : 'carte'} sur{' '}
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
            {sheet.map((identity) => (
              <Placeholder key={identity.id} identity={identity} withPicture={withPicture} />
            ))}
          </ul>
        ))}
      </div>
    </div>
  )
}
