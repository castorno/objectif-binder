import { useId, useState } from 'react'
import type { SetPullRates } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { FormError } from '../../components/FormError'
import { SetLogo } from '../../components/SetLogo'
import { useNotify } from '../../components/useNotify'
import { panelClasses } from './panelClasses'
import { useSaveSetImage } from './useSaveSetImage'

/** Same limit as the API. */
const MAX_IMAGE_BYTES = 2 * 1024 * 1024

/**
 * The picture that stands for the set: its imported logo, unless an
 * administrator sends another. Sent as soon as it is chosen.
 */
export function SetImageField({ pullRates }: { pullRates: SetPullRates }) {
  const notify = useNotify()
  const inputId = useId()
  const hintId = useId()
  const [tooLarge, setTooLarge] = useState(false)
  const save = useSaveSetImage(pullRates.set.id, (sent) =>
    notify({ title: sent ? 'Image enregistrée' : 'Image retirée', detail: pullRates.set.name }),
  )

  return (
    <section aria-labelledby="set-image-title" className={panelClasses}>
      <h3 id="set-image-title" className="text-sm font-medium text-muted">
        Image de l'extension
      </h3>
      {pullRates.imageUrl === null ? (
        <p className="text-sm text-muted">Aucune image : le nom de l'extension est affiché seul.</p>
      ) : (
        <div className="flex h-20 items-center rounded-lg border border-line bg-sunken px-4">
          <SetLogo url={pullRates.imageUrl} className="max-h-14 max-w-full" />
        </div>
      )}
      <div className="flex flex-wrap items-center gap-3">
        <label htmlFor={inputId} className={`${buttonStyles.secondary} cursor-pointer has-[:disabled]:opacity-50`}>
          {save.isPending ? 'Envoi…' : pullRates.hasCustomImage ? "Remplacer l'image" : 'Envoyer une image'}
          <input
            id={inputId}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            aria-describedby={hintId}
            disabled={save.isPending}
            className="sr-only"
            onChange={(event) => {
              const file = event.target.files?.[0]
              // The same file can be chosen again after a failure.
              event.target.value = ''
              if (file === undefined) return

              setTooLarge(file.size > MAX_IMAGE_BYTES)
              if (file.size <= MAX_IMAGE_BYTES) save.mutate(file)
            }}
          />
        </label>
        {pullRates.hasCustomImage && (
          <button
            type="button"
            disabled={save.isPending}
            onClick={() => save.mutate(null)}
            className="rounded-md px-1 py-2 text-sm font-medium text-danger underline-offset-4 hover:underline disabled:opacity-50"
          >
            Retirer l'image
          </button>
        )}
      </div>
      <p id={hintId} className="text-sm text-muted">
        PNG, JPEG ou WebP, 2 Mo au plus. Elle remplace le logo importé avec l'extension, qui revient si vous la retirez.
      </p>
      {(tooLarge || save.isError) && (
        <FormError>
          {tooLarge
            ? 'Cette image dépasse 2 Mo.'
            : "L'image n'a pas pu être enregistrée. Vérifiez que c'est bien une image PNG, JPEG ou WebP."}
        </FormError>
      )}
    </section>
  )
}
