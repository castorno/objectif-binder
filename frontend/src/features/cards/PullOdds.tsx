import { oddsToPercent, triesForProbability } from '../../lib/odds'

const integerFormatter = new Intl.NumberFormat('fr-FR')
// Odds are not always a whole number: 4 commons per booster among 66 is 1 in 16.5.
const oddsFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 })
const percentFormatter = new Intl.NumberFormat('fr-FR', { maximumSignificantDigits: 2 })

export function PullOdds({
  oneIn,
  source = null,
  setName,
}: {
  oneIn: number | null
  /** Where the rate comes from, when it was said. */
  source?: string | null
  setName: string
}) {
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
          {oneIn <= 1 ? (
            // More cards of the rarity in a booster than the set has of them.
            <p className="mt-1 text-2xl font-semibold tracking-tight">Dans chaque booster</p>
          ) : (
            <>
              <p className="mt-1 text-2xl font-semibold tracking-tight">
                1 chance sur {oddsFormatter.format(oneIn)}
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
          {/* Publishers rarely give their rates: the figure is an estimate, and says whose. */}
          <p className="mt-2 text-xs text-muted">
            Estimation, pas un taux officiel.{source !== null && ` Source : ${source}.`}
          </p>
        </>
      )}
    </section>
  )
}
