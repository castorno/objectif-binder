import { oddsToPercent, triesForProbability } from '../../lib/odds'

const integerFormatter = new Intl.NumberFormat('fr-FR')
const percentFormatter = new Intl.NumberFormat('fr-FR', { maximumSignificantDigits: 2 })

export function PullOdds({ oneIn, setName }: { oneIn: number | null; setName: string }) {
  return (
    <section aria-labelledby="pull-odds-title" className="rounded-xl border border-line bg-surface p-5">
      <h2 id="pull-odds-title" className="text-sm font-medium text-muted">
        Probabilité d'obtention
      </h2>

      {oneIn === null ? (
        <p className="mt-2 text-sm text-muted">
          Le taux d'obtention de cette rareté n'est pas renseigné pour l'extension {setName}.
        </p>
      ) : (
        <>
          <p className="mt-1 text-2xl font-semibold tracking-tight">
            1 chance sur {integerFormatter.format(oneIn)}
            <span className="ml-2 text-base font-normal text-muted">
              ({percentFormatter.format(oddsToPercent(oneIn))} %) par booster
            </span>
          </p>
          <p className="mt-2 text-sm text-muted">
            Chance d'obtenir précisément cette carte en ouvrant un booster de l'extension {setName}. Il faut
            ouvrir environ {integerFormatter.format(triesForProbability(oneIn))} boosters pour avoir une chance
            sur deux de la trouver.
          </p>
        </>
      )}
    </section>
  )
}
