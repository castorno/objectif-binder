import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { collectionCompletionQuery } from '../../api/queries'
import type { CardCondition } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { FormError } from '../../components/FormError'
import { ProgressTrack } from '../../components/ProgressTrack'
import { SelectField } from '../../components/SelectField'
import { pageTitle } from '../../config'
import { noFilters, OWNED_CARDS_PATH } from '../cards/useCardSearchParams'
import { CONDITIONS } from '../collection/conditions'
import { useLogout, useSavePreferences, useSession } from './useSession'
import { formatCount, plural } from '../../lib/counts'


/** The search that narrows nothing: its completion is that of the whole catalogue. */
const WHOLE_CATALOGUE = noFilters

const panelClasses = 'rounded-xl border border-line bg-surface'

/** Rendered under RequireAuth, so there is always a signed-in user here. */
export function AccountPage() {
  const { user } = useSession()
  const logout = useLogout()
  const savePreferences = useSavePreferences()
  // An extra of the page: if it cannot be loaded, the rest is shown without it.
  const completion = useQuery({ ...collectionCompletionQuery(WHOLE_CATALOGUE), enabled: user !== null })

  if (user === null) return null

  const shortcuts = [
    { to: OWNED_CARDS_PATH, label: 'Voir ma collection' },
    { to: '/boosters', label: 'Quel booster ouvrir ?' },
    ...(user.isAdmin ? [{ to: '/admin/pull-rates', label: 'Taux de drop' }] : []),
  ]

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-4">
      <title>{pageTitle('Mon compte')}</title>

      <h1 className="mb-2 text-3xl font-semibold tracking-tight">Mon compte</h1>

      {completion.isPending && <div aria-hidden="true" className="h-28 rounded-xl bg-sunken motion-safe:animate-pulse" />}
      {completion.isSuccess && completion.data.total > 0 && (
        <section aria-labelledby="account-collection-title" className={`${panelClasses} p-5`}>
          <h2 id="account-collection-title" className="text-sm font-medium text-muted">
            Ma collection
          </h2>
          <p className="mt-1 flex flex-wrap items-baseline gap-x-2">
            <span className="text-3xl font-bold tabular-nums">{formatCount(completion.data.owned)}</span>
            <span className="text-sm">
              {plural(completion.data.owned, 'carte', 'cartes')} sur {formatCount(completion.data.total)}
            </span>
          </p>
          <ProgressTrack percent={(completion.data.owned / completion.data.total) * 100} className="mt-3 h-2 w-full" />
        </section>
      )}

      <nav aria-label="Raccourcis" className={panelClasses}>
        <ul className="divide-y divide-line">
          {shortcuts.map((shortcut) => (
            // The hover of the first and last rows follows the corners of the panel.
            <li key={shortcut.to} className="first:[&>a]:rounded-t-xl last:[&>a]:rounded-b-xl">
              <Link
                to={shortcut.to}
                className="flex items-center justify-between gap-4 px-5 py-3 text-sm font-medium transition-colors hover:bg-sunken"
              >
                {shortcut.label}
                <span aria-hidden="true" className="text-muted">
                  →
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </nav>

      <section aria-labelledby="account-preferences-title" className={`${panelClasses} p-5`}>
        <h2 id="account-preferences-title" className="mb-3 text-sm font-medium text-muted">
          Préférences
        </h2>
        <SelectField
          label="État d'une carte ajoutée"
          value={user.defaultCondition ?? ''}
          // Saved as soon as chosen; one change at a time.
          onChange={(value) => savePreferences.mutate({ defaultCondition: (value || null) as CardCondition | null })}
          options={CONDITIONS}
          allLabel="Aucun"
          disabled={savePreferences.isPending}
          describedBy="account-default-condition-help"
        />
        <p id="account-default-condition-help" className="mt-2 text-sm text-muted">
          L'état qu'une carte reçoit quand vous l'ajoutez à votre collection. Il reste modifiable carte par carte.
        </p>
        <p role="status" className="sr-only">
          {savePreferences.isSuccess ? 'Préférence enregistrée.' : ''}
        </p>
        {savePreferences.isError && (
          <FormError className="mt-2">
            Enregistrement impossible. Réessayez.
          </FormError>
        )}
      </section>

      <section aria-labelledby="account-identity-title" className={panelClasses}>
        <h2 id="account-identity-title" className="sr-only">
          Identifiants
        </h2>
        <dl>
          <div className="flex flex-wrap justify-between gap-x-4 gap-y-1 px-5 py-4 text-sm">
            <dt className="text-muted">Adresse e-mail</dt>
            <dd className="font-medium break-all">{user.email}</dd>
          </div>
        </dl>
        <div className="flex flex-wrap items-center gap-3 border-t border-line px-5 py-4">
          <button
            type="button"
            onClick={() => logout.mutate()}
            disabled={logout.isPending}
            className={`${buttonStyles.secondary} disabled:opacity-60`}
          >
            Se déconnecter
          </button>
          {logout.isError && (
            <FormError>
              Déconnexion impossible. Réessayez.
            </FormError>
          )}
        </div>
      </section>
    </div>
  )
}
