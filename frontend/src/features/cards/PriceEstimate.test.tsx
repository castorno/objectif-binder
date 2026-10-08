import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { CardPrice } from '../../api/types'
import { emberFox } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

const PRICE: CardPrice = {
  marketplace: 'Cardmarket',
  currency: 'EUR',
  trendCents: 12050,
  lowCents: 9000,
  average30DaysCents: 11875,
  holoTrendCents: null,
  holoLowCents: null,
  holoAverage30DaysCents: null,
  sourceUpdatedAt: '2026-10-08T09:52:36+00:00',
  fetchedAt: '2026-10-08T12:00:00+00:00',
}

function havePrice(price: CardPrice | null) {
  const requests: Request[] = []
  server.use(
    http.get('*/api/cards/:id/price', ({ request }) => {
      requests.push(request)

      return HttpResponse.json({ price })
    }),
  )

  return requests
}

async function estimate() {
  return within(await screen.findByRole('region', { name: 'Prix estimé' }))
}

/** Amounts are formatted with narrow no-break spaces: compare on what is read. */
function text(element: HTMLElement): string {
  return (element.textContent ?? '').replace(/\s/g, ' ')
}

describe('estimated price of a card', () => {
  it('shows a signed-in user what the card sells for, and where the figure comes from', async () => {
    signInAs()
    havePrice(PRICE)
    renderApp(`/cards/${emberFox.id}`)

    const block = await estimate()

    // The monthly average first: a single sale moves it less than the recent price.
    expect(text(block.getByText(/118,75/))).toBe('118,75 €')
    expect(text(block.getByText(/moyenne sur 30 jours/))).toBe('moyenne sur 30 jours · tendance 120,50 € · à partir de 90,00 €')
    expect(block.queryByText(/Prix très variable/)).not.toBeInTheDocument()
    // Not a quote: the mix it stands for and its source are always said.
    expect(text(block.getByText(/Carte non gradée/))).toBe(
      'Carte non gradée, toutes langues et tous états confondus. Source : Cardmarket, 8 octobre 2026.',
    )
  })

  it('tells the shiny version apart when the market does', async () => {
    signInAs()
    havePrice({ ...PRICE, trendCents: 7, lowCents: 2, average30DaysCents: 8, holoTrendCents: 27, holoLowCents: 4, holoAverage30DaysCents: 29 })
    renderApp(`/cards/${emberFox.id}`)

    const block = await estimate()

    expect(text(block.getByText('Normale').closest('p')!)).toContain('0,08 €')
    expect(text(block.getByText('Brillante (holo ou reverse)').closest('p')!)).toContain('0,29 €')
  })

  it('warns when the recent price strays far from the monthly average', async () => {
    signInAs()
    // One sale at several times the usual price is enough to do this.
    havePrice({ ...PRICE, trendCents: 70790, lowCents: 3000, average30DaysCents: 57996 })
    renderApp(`/cards/${emberFox.id}`)

    const block = await estimate()

    expect(text(block.getByText(/579,96/))).toBe('579,96 €')
    expect(block.getByText('Prix très variable sur cette carte : à prendre avec prudence.')).toBeInTheDocument()
  })

  it('falls back on another figure when the monthly average is missing', async () => {
    signInAs()
    havePrice({ ...PRICE, average30DaysCents: null })
    renderApp(`/cards/${emberFox.id}`)

    const block = await estimate()

    expect(text(block.getByText(/120,50/))).toBe('120,50 €')
    expect(text(block.getByText(/à partir de/))).toBe('à partir de 90,00 €')
  })

  it('shows nothing for a card without price', async () => {
    signInAs()
    havePrice(null)
    renderApp(`/cards/${emberFox.id}`)

    await screen.findByRole('region', { name: 'Ma collection' })
    expect(screen.queryByRole('region', { name: 'Prix estimé' })).not.toBeInTheDocument()
  })

  it('shows a visitor no price, and does not ask the API for one', async () => {
    const requests = havePrice(PRICE)
    renderApp(`/cards/${emberFox.id}`)

    await screen.findByRole('link', { name: 'Connectez-vous' })
    expect(screen.queryByRole('region', { name: 'Prix estimé' })).not.toBeInTheDocument()
    expect(requests).toHaveLength(0)
  })

  it('leaves the page as it is when the price cannot be fetched', async () => {
    signInAs()
    server.use(http.get('*/api/cards/:id/price', () => HttpResponse.json({ error: 'Internal error.' }, { status: 500 })))
    renderApp(`/cards/${emberFox.id}`)

    expect(await screen.findByRole('heading', { name: emberFox.name })).toBeInTheDocument()
    await screen.findByRole('region', { name: 'Ma collection' })
    expect(screen.queryByRole('region', { name: 'Prix estimé' })).not.toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
