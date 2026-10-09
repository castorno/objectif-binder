import { Navigate, Outlet } from 'react-router'
import { StateMessage } from '../../components/StateMessage'
import { useReturnHere } from '../auth/destination'
import { useSession } from '../auth/useSession'

/**
 * Route guard for the administration. Like RequireAuth, a convenience and
 * not a protection: the API answers 403 to anyone without the role,
 * whatever the browser shows.
 */
export function RequireAdmin() {
  const { user, isPending } = useSession()
  const returnHere = useReturnHere()

  if (isPending) {
    return (
      <p role="status" className="text-sm text-muted">
        Chargement…
      </p>
    )
  }

  if (user === null) return <Navigate to="/login" state={returnHere} replace />

  if (!user.isAdmin) {
    return <StateMessage title="Page réservée aux administrateurs">Votre compte n'y a pas accès.</StateMessage>
  }

  return <Outlet />
}
