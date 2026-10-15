import { useId } from 'react'
import { Link } from 'react-router'
import type { CardSet, SetPullRates } from '../../api/types'
import { ComboboxField } from '../../components/ComboboxField'
import { FormError } from '../../components/FormError'
import { useNotify } from '../../components/useNotify'
import { setOptions } from '../cards/setOptions'
import { panelClasses } from './panelClasses'
import { pullRatesPath } from './pullRatesPath'
import { useSaveSetParent } from './useSaveSetParent'
import { plural } from '../../lib/counts'

/**
 * Says which set the cards of this one come in the boosters of. Saved as
 * soon as it is picked: it is one answer, not a form.
 */
export function ParentSetField({ pullRates, sets, gameSlug }: { pullRates: SetPullRates; sets: CardSet[]; gameSlug: string }) {
  const notify = useNotify()
  const save = useSaveSetParent(pullRates.set.id, (parentId) => {
    const parent = sets.find((set) => set.id === parentId)
    notify({
      title: parent === undefined ? 'Extension détachée' : 'Extension liée',
      detail: parent === undefined ? pullRates.set.name : `${pullRates.set.name} → ${parent.name}`,
    })
  })
  const hintId = useId()
  const hasSubSets = pullRates.subSets.length > 0
  // One level only: a parent is a set that comes with no other.
  const candidates = sets.filter((set) => set.parentCode === null && set.code !== pullRates.set.code)

  return (
    <section aria-labelledby="parent-set-title" className={panelClasses}>
      <h3 id="parent-set-title" className="text-sm font-medium text-muted">
        Extension liée
      </h3>
      {hasSubSets ? (
        <p className="text-sm">
          Comprend {plural(pullRates.subSets.length, 'la sous-extension', 'les sous-extensions')} :{' '}
          {pullRates.subSets.map((subSet, index) => (
            <span key={subSet.id}>
              {index > 0 && ', '}
              <Link
                to={pullRatesPath(gameSlug, subSet.code)}
                className="rounded-md font-medium text-accent underline-offset-4 hover:underline"
              >
                {subSet.name}
              </Link>
            </span>
          ))}
          . {plural(pullRates.subSets.length, 'Ses', 'Leurs')} cartes sont comptées dans le tableau ci-dessus.
        </p>
      ) : (
        <>
          <ComboboxField
            label="Ses cartes sortent des boosters de"
            allLabel="Ses propres boosters"
            emptyMessage="Aucune extension ne correspond."
            value={pullRates.parent?.code ?? ''}
            disabled={save.isPending}
            describedBy={hintId}
            onChange={(code) => save.mutate(sets.find((set) => set.code === code)?.id ?? null)}
            options={setOptions(candidates)}
          />
          <p id={hintId} className="text-sm text-muted">
            Pour une galerie ou une collection classique sortie dans les boosters d'une autre extension : ses
            cartes apparaîtront avec celles de l'extension principale.
          </p>
        </>
      )}
      {save.isError && (
        <FormError>
          Le lien n'a pas pu être enregistré. Réessayez.
        </FormError>
      )}
    </section>
  )
}
