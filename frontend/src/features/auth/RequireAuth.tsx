import { Navigate, Outlet } from 'react-router'
import { useReturnHere } from './destination'
import { useSession } from './useSession'

/**
 * Route guard for the pages that need a signed-in user: a visitor is sent to
 * the sign-in screen, then brought back to the page they asked for.
 *
 * This is a convenience for the user, not a protection: the data is guarded
 * by the API, which answers 401 to any request without a valid token whatever
 * the browser shows.
 */
export function RequireAuth() {
  const { user, isPending } = useSession()
  const returnHere = useReturnHere()

  // The session is being restored (page reload): sending a signed-in user to
  // the sign-in screen for an instant would be wrong.
  if (isPending) {
    return (
      <p role="status" className="text-sm text-muted">
        Chargement…
      </p>
    )
  }

  if (user === null) return <Navigate to="/login" state={returnHere} replace />

  return <Outlet />
}
