import { useState, type FormEvent } from 'react'
import type { SetPullRates } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { FormError } from '../../components/FormError'
import { TextField } from '../../components/TextField'
import { useNotify } from '../../components/useNotify'
import { panelClasses } from './panelClasses'
import { useSaveForcedRarity } from './useSaveForcedRarity'

/**
 * Names the rarity all the cards of a sub-set get: they come out of the
 * boosters of the main set at a rate of their own, which needs a rarity of
 * their own to be entered.
 */
export function ForcedRarityField({ pullRates }: { pullRates: SetPullRates }) {
  const notify = useNotify()
  const save = useSaveForcedRarity(pullRates.set.id, (saved) =>
    notify(
      saved === null
        ? { title: 'Raretés rendues à la source', detail: "Elles reviendront au prochain import de l'extension." }
        : { title: 'Rareté appliquée', detail: `${pullRates.set.name} : ${saved}` },
    ),
  )
  const [name, setName] = useState(pullRates.forcedRarity ?? '')
  const changed = name.trim() !== (pullRates.forcedRarity ?? '')

  function onSubmit(event: FormEvent) {
    event.preventDefault()
    const typed = name.trim()

    save.mutate(typed === '' ? null : typed)
  }

  return (
    <form onSubmit={onSubmit} className={panelClasses}>
      <TextField
        label="Rareté de ses cartes"
        value={name}
        maxLength={100}
        placeholder="Celles de la source"
        onChange={(event) => setName(event.target.value)}
        hint="Par exemple « Reprint » ou « Galerie de Dresseurs ». Toutes ses cartes prennent cette rareté, qui a alors sa propre ligne dans les taux de l'extension principale. Laissez vide pour garder les raretés de la source."
      />
      {save.isError && (
        <FormError>
          La rareté n'a pas pu être appliquée. Réessayez.
        </FormError>
      )}
      <div>
        <button
          type="submit"
          disabled={!changed || save.isPending}
          className={`${buttonStyles.secondary} disabled:cursor-not-allowed disabled:opacity-50`}
        >
          {save.isPending ? 'Application…' : 'Appliquer'}
        </button>
      </div>
    </form>
  )
}
