import { useQuery } from '@tanstack/react-query'
import { useId, useRef, useState } from 'react'
import { Link } from 'react-router'
import { ownedCardsQuery } from '../../api/queries'
import type { CardCondition, CardFinish, Discovery, OwnedCard } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { useNotify, type Notification } from '../../components/useNotify'
import { useReturnHere } from '../auth/destination'
import { useSession } from '../auth/useSession'
import { CONDITIONS } from './conditions'
import { finishesToOffer, finishLabel } from './finishes'
import { languageLabel, LANGUAGES } from './languages'
import { useRemoveOwnedCard, useSaveOwnedCard } from './useOwnedCards'

/** Same bound as the API. */
const MAX_QUANTITY = 9999

const stepButtonClasses =
  'flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-surface text-lg font-medium transition-colors hover:bg-sunken disabled:cursor-not-allowed disabled:opacity-50'
const selectClasses = 'h-10 rounded-lg border border-line bg-surface px-3 text-sm disabled:opacity-50'

function copies(quantity: number): string {
  return `${quantity} ${quantity > 1 ? 'exemplaires' : 'exemplaire'}`
}

const countFormatter = new Intl.NumberFormat('fr-FR')
const nameList = new Intl.ListFormat('fr-FR', { type: 'conjunction' })

/** The first card of an identity is a small event: say it, and how far it takes the user. */
function discoveryNotification({ identities, label, started, total }: Discovery): Notification {
  return {
    title: `Première carte ${nameList.format(identities.map((identity) => identity.name))} dans votre collection`,
    detail: `${label ?? 'Progression'} : ${countFormatter.format(started)} sur ${countFormatter.format(total)}`,
  }
}

/**
 * The "Ma collection" block of a card page: what the user owns of this card.
 *
 * @param finishes the finishes the card was printed with, null when unknown
 */
export function OwnedCardPanel({ cardId, finishes = null }: { cardId: string; finishes?: CardFinish[] | null }) {
  const { user, isPending } = useSession()
  const returnHere = useReturnHere()

  return (
    <section aria-labelledby="owned-card-title" className="rounded-xl border border-line bg-surface p-5">
      <h2 id="owned-card-title" className="text-sm font-medium text-muted">
        Ma collection
      </h2>

      {/* Nothing while the session is being restored, rather than a sign-in
          prompt flashed at a signed-in user. */}
      {!isPending && user === null && (
        <p className="mt-2 text-sm">
          <Link to="/login" state={returnHere} className="rounded-md font-medium text-accent underline-offset-4 hover:underline">
            Connectez-vous
          </Link>{' '}
          pour ajouter cette carte à votre collection.
        </p>
      )}
      {user !== null && <OwnedCardEditor cardId={cardId} finishes={finishesToOffer(finishes)} />}
    </section>
  )
}

function OwnedCardEditor({ cardId, finishes }: { cardId: string; finishes: CardFinish[] }) {
  const languageId = useId()
  const finishId = useId()
  const languageSelect = useRef<HTMLSelectElement>(null)
  const owned = useQuery(ownedCardsQuery(cardId))
  const save = useSaveOwnedCard(cardId)
  const remove = useRemoveOwnedCard(cardId)
  const notify = useNotify()
  // Read out by screen readers, which would otherwise not notice a change.
  const [announcement, setAnnouncement] = useState('')
  const [chosenLanguage, setChosenLanguage] = useState('')
  const [chosenFinish, setChosenFinish] = useState<CardFinish | ''>('')

  if (owned.isPending) {
    return (
      <p role="status" className="mt-2 text-sm text-muted">
        Chargement de votre collection…
      </p>
    )
  }

  if (owned.isError) {
    return (
      <div role="alert" className="mt-2 flex flex-wrap items-center gap-3 text-sm text-danger">
        <p>Impossible de charger votre collection pour cette carte.</p>
        <button type="button" onClick={() => void owned.refetch()} className={buttonStyles.secondary}>
          Réessayer
        </button>
      </div>
    )
  }

  // One change at a time: the controls wait for the API's answer, so two
  // requests can never arrive in the wrong order.
  const isBusy = save.isPending || remove.isPending
  // A card with a single finish says nothing about it: there is nothing to tell apart.
  const tellsFinishesApart = finishes.length > 1 || owned.data.some((entry) => entry.finish !== finishes[0])
  const entryLabel = (entry: { language: string; finish: CardFinish }) =>
    tellsFinishesApart ? `${languageLabel(entry.language)}, ${finishLabel(entry.finish)}` : languageLabel(entry.language)

  // What can still be added: the finishes not owned yet, language by language.
  const freeFinishes = (language: string) =>
    finishes.filter((finish) => !owned.data.some((entry) => entry.language === language && entry.finish === finish))
  const availableLanguages = LANGUAGES.filter(({ code }) => freeFinishes(code).length > 0)
  const languageToAdd = availableLanguages.some(({ code }) => code === chosenLanguage)
    ? chosenLanguage
    : availableLanguages[0]?.code
  const finishesToAdd = languageToAdd === undefined ? [] : freeFinishes(languageToAdd)
  const finishToAdd = finishesToAdd.find((finish) => finish === chosenFinish) ?? finishesToAdd[0]

  function saveEntry(entry: { language: string; finish: CardFinish }, quantity: number, condition: CardCondition | null) {
    remove.reset()
    save.mutate(
      { language: entry.language, finish: entry.finish, quantity, condition },
      {
        onSuccess: ({ discovery }) => {
          setAnnouncement(`${entryLabel(entry)} : ${copies(quantity)} dans votre collection.`)
          if (discovery !== null) notify(discoveryNotification(discovery))
        },
      },
    )
  }

  function removeEntry(entry: OwnedCard) {
    save.reset()
    remove.mutate(
      { language: entry.language, finish: entry.finish },
      {
        onSuccess: () => {
          setAnnouncement(`${entryLabel(entry)} retiré de votre collection.`)
          // The button that had the focus is gone with its row.
          languageSelect.current?.focus()
        },
      },
    )
  }

  return (
    <>
      {owned.data.length === 0 ? (
        <p className="mt-2 text-sm text-muted">Vous ne possédez pas encore cette carte.</p>
      ) : (
        <ul className="mt-1 divide-y divide-line">
          {owned.data.map((entry) => {
            const label = entryLabel(entry)

            return (
              <li key={`${entry.language} ${entry.finish}`} className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                <span className="min-w-24 flex-1 font-medium">
                  {languageLabel(entry.language)}
                  {tellsFinishesApart && <span className="ml-2 text-sm font-normal text-muted">{finishLabel(entry.finish)}</span>}
                </span>

                <div role="group" aria-label={`Quantité (${label})`} className="flex items-center gap-1">
                  <button
                    type="button"
                    aria-label={`Retirer un exemplaire (${label})`}
                    // Going below one is the job of the "Retirer" button: a
                    // click too many must not delete the entry.
                    disabled={isBusy || entry.quantity <= 1}
                    onClick={() => saveEntry(entry, entry.quantity - 1, entry.condition)}
                    className={stepButtonClasses}
                  >
                    <span aria-hidden="true">−</span>
                  </button>
                  <span className="min-w-10 text-center text-lg font-semibold tabular-nums">
                    {entry.quantity}
                    <span className="sr-only"> {entry.quantity > 1 ? 'exemplaires' : 'exemplaire'}</span>
                  </span>
                  <button
                    type="button"
                    aria-label={`Ajouter un exemplaire (${label})`}
                    disabled={isBusy || entry.quantity >= MAX_QUANTITY}
                    onClick={() => saveEntry(entry, entry.quantity + 1, entry.condition)}
                    className={stepButtonClasses}
                  >
                    <span aria-hidden="true">+</span>
                  </button>
                </div>

                <select
                  aria-label={`État (${label})`}
                  value={entry.condition ?? ''}
                  disabled={isBusy}
                  onChange={(event) => saveEntry(entry, entry.quantity, (event.target.value || null) as CardCondition | null)}
                  className={selectClasses}
                >
                  <option value="">État non précisé</option>
                  {CONDITIONS.map((condition) => (
                    <option key={condition.value} value={condition.value}>
                      {condition.label}
                    </option>
                  ))}
                </select>

                <button
                  type="button"
                  aria-label={`Retirer ${label} de ma collection`}
                  disabled={isBusy}
                  onClick={() => removeEntry(entry)}
                  className="rounded-md px-1 py-2 text-sm font-medium text-danger underline-offset-4 hover:underline disabled:opacity-50"
                >
                  Retirer
                </button>
              </li>
            )
          })}
        </ul>
      )}

      {languageToAdd !== undefined && finishToAdd !== undefined && (
        <form
          onSubmit={(event) => {
            event.preventDefault()
            saveEntry({ language: languageToAdd, finish: finishToAdd }, 1, null)
            // The next copy to add starts from the first finish again, not from the one just picked.
            setChosenFinish('')
          }}
          className={`flex flex-wrap items-end gap-3 ${owned.data.length > 0 ? 'border-t border-line pt-4' : 'mt-3'}`}
        >
          <div className="flex flex-col gap-1.5">
            <label htmlFor={languageId} className="text-sm font-medium">
              Langue
            </label>
            <select
              id={languageId}
              ref={languageSelect}
              value={languageToAdd}
              onChange={(event) => setChosenLanguage(event.target.value)}
              className={selectClasses}
            >
              {availableLanguages.map(({ code, label }) => (
                <option key={code} value={code}>
                  {label}
                </option>
              ))}
            </select>
          </div>
          {/* Asked only when the card exists in more than one finish. */}
          {finishes.length > 1 && (
            <div className="flex flex-col gap-1.5">
              <label htmlFor={finishId} className="text-sm font-medium">
                Finition
              </label>
              <select
                id={finishId}
                value={finishToAdd}
                onChange={(event) => setChosenFinish(event.target.value as CardFinish)}
                className={selectClasses}
              >
                {finishesToAdd.map((finish) => (
                  <option key={finish} value={finish}>
                    {finishLabel(finish)}
                  </option>
                ))}
              </select>
            </div>
          )}
          <button type="submit" disabled={isBusy} className={`${buttonStyles.primary} h-10 disabled:opacity-60`}>
            {owned.data.length === 0 ? 'Ajouter à ma collection' : finishes.length > 1 ? 'Ajouter' : 'Ajouter cette langue'}
          </button>
        </form>
      )}

      {(save.isError || remove.isError) && (
        <p role="alert" className="mt-3 text-sm text-danger">
          La modification n'a pas pu être enregistrée. Réessayez.
        </p>
      )}
      <p role="status" className="sr-only">
        {announcement}
      </p>
    </>
  )
}
