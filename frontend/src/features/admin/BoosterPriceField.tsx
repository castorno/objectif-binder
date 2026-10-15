import { useState } from 'react'
import type { SetPullRates } from '../../api/types'
import { buttonStyles } from '../../components/buttonStyles'
import { FormError } from '../../components/FormError'
import { TextField } from '../../components/TextField'
import { useNotify } from '../../components/useNotify'
import { formatEuros, parseEuros } from '../../lib/money'
import { panelClasses } from './panelClasses'
import { useSaveBoosterPrice } from './useSaveBoosterPrice'

/**
 * What a booster of the set costs. Entered by hand: no source gives it, and
 * it is what lets the boosters be compared for what they bring per euro.
 */
export function BoosterPriceField({ pullRates }: { pullRates: SetPullRates }) {
  const notify = useNotify()
  const save = useSaveBoosterPrice(pullRates.set.id, (saved) =>
    notify(
      saved === null
        ? { title: 'Prix retiré', detail: pullRates.set.name }
        : { title: 'Prix enregistré', detail: `${pullRates.set.name} : ${formatEuros(saved)} le booster` },
    ),
  )
  const savedText = pullRates.boosterPriceCents === null ? '' : (pullRates.boosterPriceCents / 100).toFixed(2).replace('.', ',')
  const [price, setPrice] = useState(savedText)

  const typed = price.trim()
  const cents = typed === '' ? null : parseEuros(typed)
  const isInvalid = typed !== '' && (cents === null || cents === 0)
  const changed = cents !== pullRates.boosterPriceCents

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        if (!isInvalid) save.mutate(cents)
      }}
      noValidate
      className={panelClasses}
    >
      <TextField
        label="Prix d'un booster (€)"
        value={price}
        inputMode="decimal"
        placeholder="Non renseigné"
        onChange={(event) => setPrice(event.target.value)}
        error={isInvalid ? 'Un prix en euros, par exemple 5,99.' : undefined}
        hint="Sert à comparer les boosters par euro dépensé. Aucune source ne le donne : indiquez le prix que vous payez."
      />
      {save.isError && (
        <FormError>
          Le prix n'a pas pu être enregistré. Réessayez.
        </FormError>
      )}
      <div>
        <button
          type="submit"
          disabled={!changed || isInvalid || save.isPending}
          className={`${buttonStyles.secondary} disabled:cursor-not-allowed disabled:opacity-50`}
        >
          {save.isPending ? 'Enregistrement…' : 'Enregistrer le prix'}
        </button>
      </div>
    </form>
  )
}
