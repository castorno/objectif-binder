import { buttonStyles } from '../../components/buttonStyles'
import { pageTitle } from '../../config'
import { useLogout, useSession } from './useSession'

/** Rendered under RequireAuth, so there is always a signed-in user here. */
export function AccountPage() {
  const { user } = useSession()
  const logout = useLogout()

  if (user === null) return null

  return (
    <div className="mx-auto max-w-xl">
      <title>{pageTitle('Mon compte')}</title>

      <h1 className="text-3xl font-semibold tracking-tight">Mon compte</h1>

      <dl className="mt-6 rounded-xl border border-line bg-surface">
        <div className="flex flex-wrap justify-between gap-x-4 gap-y-1 px-5 py-4 text-sm">
          <dt className="text-muted">Adresse e-mail</dt>
          <dd className="font-medium break-all">{user.email}</dd>
        </div>
      </dl>

      <p className="mt-4 text-sm text-muted">Votre collection et vos favoris apparaîtront ici.</p>

      <div className="mt-6 flex flex-wrap items-center gap-3">
        <button
          type="button"
          onClick={() => logout.mutate()}
          disabled={logout.isPending}
          className={`${buttonStyles.secondary} disabled:opacity-60`}
        >
          Se déconnecter
        </button>
        {logout.isError && (
          <p role="alert" className="text-sm text-danger">
            Déconnexion impossible. Réessayez.
          </p>
        )}
      </div>
    </div>
  )
}
