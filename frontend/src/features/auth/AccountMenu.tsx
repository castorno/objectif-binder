import { Link } from 'react-router'
import { buttonStyles } from '../../components/buttonStyles'
import { useReturnHere } from './destination'
import { useLogout, useSession } from './useSession'

/** The account corner of the site header. */
export function AccountMenu() {
  const { user, isPending } = useSession()
  const logout = useLogout()
  const returnHere = useReturnHere()

  // Nothing while the session is being restored: showing a signed-out state
  // for an instant to a signed-in user would be wrong.
  if (isPending) return null

  if (user === null) {
    return (
      // Signing in brings the visitor back to the page they were on.
      <Link to="/login" state={returnHere} className={buttonStyles.secondary}>
        Se connecter
      </Link>
    )
  }

  return (
    <div className="flex min-w-0 items-center gap-3">
      {logout.isError && (
        <p role="alert" className="text-sm text-danger">
          Déconnexion impossible. Réessayez.
        </p>
      )}
      <p className="min-w-0 text-sm text-muted">
        <span className="sr-only">Connecté en tant que </span>
        {/* Too long for a phone header; still announced to screen readers. */}
        <span className="sr-only sm:not-sr-only sm:block sm:max-w-56 sm:truncate">{user.email}</span>
      </p>
      <button
        type="button"
        onClick={() => logout.mutate()}
        disabled={logout.isPending}
        className={`${buttonStyles.secondary} shrink-0 disabled:opacity-60`}
      >
        Se déconnecter
      </button>
    </div>
  )
}
