import { Link } from 'react-router'
import { pageTitle } from '../config'
import { buttonStyles } from './buttonStyles'
import { StateMessage } from './StateMessage'

export function NotFoundPage() {
  return (
    <>
      <title>{pageTitle('Page introuvable')}</title>
      <StateMessage
        title="Page introuvable"
        action={
          <Link to="/" className={buttonStyles.primary}>
            Retour au catalogue
          </Link>
        }
      >
        Cette adresse ne correspond à aucune page.
      </StateMessage>
    </>
  )
}
