import { useQuery } from '@tanstack/react-query'
import { identityQuery } from '../../api/queries'
import { WITHOUT_IDENTITY } from '../../api/types'

type IdentityFilterProps = {
  /** Id of an identity, or WITHOUT_IDENTITY. */
  identity: string
  onRemove: () => void
}

/**
 * Says which identity the catalogue is narrowed to, and lets it go. The way
 * back to the grouped view is the view switch, just above.
 */
export function IdentityFilter({ identity, onRemove }: IdentityFilterProps) {
  const isWithoutIdentity = identity === WITHOUT_IDENTITY
  const details = useQuery({ ...identityQuery(identity), enabled: !isWithoutIdentity })
  // The cards are listed either way: a name that cannot be loaded is not worth an error.
  const name = isWithoutIdentity ? 'Autres cartes' : (details.data?.name ?? '…')

  return (
    <p className="mt-6 inline-flex items-center gap-1 rounded-full bg-accent-soft py-1 pr-1 pl-3 text-sm font-medium">
      <span>
        <span className="sr-only">Cartes de : </span>
        {name}
      </span>
      <button
        type="button"
        onClick={onRemove}
        aria-label={`Retirer le filtre ${name}`}
        className="flex h-6 w-6 items-center justify-center rounded-full transition-colors hover:bg-surface"
      >
        <span aria-hidden="true">✕</span>
      </button>
    </p>
  )
}
