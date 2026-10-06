import { Link } from 'react-router'
import { buttonStyles } from './buttonStyles'
import { StateMessage } from './StateMessage'

export function NotFoundPage() {
  return (
    <>
      <title>Page introuvable — tcgCollector</title>
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
