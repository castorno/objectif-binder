import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { PullOdds } from './PullOdds'

describe('PullOdds', () => {
  it('shows the odds, their percentage and the boosters needed for an even chance', () => {
    render(<PullOdds oneIn={100} setName="Aube" />)

    expect(screen.getByText(/1 chance sur 100/)).toBeInTheDocument()
    expect(screen.getByText('(1 %) par booster')).toBeInTheDocument()
    expect(screen.getByText(/environ 69 boosters/)).toBeInTheDocument()
  })

  it('formats numbers the French way', () => {
    render(<PullOdds oneIn={200} setName="Aube" />)

    expect(screen.getByText('(0,5 %) par booster')).toBeInTheDocument()
  })

  it('shows odds that are not a whole number, and a card found in every booster', () => {
    // Four commons per booster among 66.
    const { rerender } = render(<PullOdds oneIn={16.5} setName="Aube" />)
    expect(screen.getByText(/1 chance sur 16,5/)).toBeInTheDocument()

    rerender(<PullOdds oneIn={1} setName="Aube" />)
    expect(screen.getByText('Dans chaque booster')).toBeInTheDocument()
    expect(screen.queryByText(/chance sur/)).not.toBeInTheDocument()
  })

  it('says the figure is an estimate, and whose', () => {
    const { rerender } = render(<PullOdds oneIn={100} setName="Aube" />)
    expect(screen.getByText('Estimation, pas un taux officiel.')).toBeInTheDocument()

    rerender(<PullOdds oneIn={100} source="Ouverture de 1 000 boosters" setName="Aube" />)
    expect(screen.getByText('Estimation, pas un taux officiel. Source : Ouverture de 1 000 boosters.')).toBeInTheDocument()
  })

  it('says so when the pull rate is unknown, instead of showing odds', () => {
    render(<PullOdds oneIn={null} setName="Aube" />)

    expect(screen.getByText(/n'est pas renseigné pour l'extension Aube/)).toBeInTheDocument()
    expect(screen.queryByText(/chance sur/)).not.toBeInTheDocument()
  })

  it('is a section named by its heading', () => {
    render(<PullOdds oneIn={100} setName="Aube" />)

    expect(screen.getByRole('region', { name: "Probabilité d'obtention" })).toBeInTheDocument()
  })
})
