import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { haveCollection, ownedCard } from '../../test/collection'
import { emberFox } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

function tile(name: string) {
  return within(screen.getByRole('heading', { level: 3, name }).closest('li')!)
}

async function progress() {
  return await screen.findByRole('region', { name: 'Progression' })
}

function completeWith(completion: { total: number; owned: number }) {
  server.use(http.get('*/api/collection/completion', () => HttpResponse.json({ ...completion, ownedOnPage: {} })))
}

describe('completion rate of a catalogue search', () => {
  it('says how many of the cards found the user owns, and shows what is owned of each', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 2), ownedCard('ja', 1)] })
    renderApp()

    // Two cards found, one owned (in two languages, which still makes one card).
    const block = await progress()
    expect(block).toHaveTextContent('50 %')
    // Shown on hover or focus; read by screen readers as the value of the bar.
    expect(block).toHaveTextContent('1 possédée · 1 manquante')
    const bar = within(block).getByRole('progressbar', { name: 'Progression' })
    expect(bar).toHaveAttribute('aria-valuenow', '1')
    expect(bar).toHaveAttribute('aria-valuemax', '2')
    expect(bar).toHaveAttribute('aria-valuetext', '1 possédée · 1 manquante')
    // On its tile, what is owned of the card in each language.
    expect(tile('Renard de braise').getByText('FR ×2')).toBeInTheDocument()
    expect(tile('Renard de braise').getByText('Japonais : 1 exemplaire')).toBeInTheDocument()
    expect(tile('Chouette des brumes').queryByText(/×/)).not.toBeInTheDocument()
  })

  it('asks for the rate of the search on screen', async () => {
    signInAs()
    const { completionRequests } = haveCollection()
    renderApp('/?q=renard&game=demo&set=AUB&page=2')

    await progress()

    const params = completionRequests[0].searchParams
    expect(Object.fromEntries(params)).toEqual({ q: 'renard', game: 'demo', set: 'AUB', page: '2', limit: '20' })
  })

  it('shows nothing about ownership to a visitor, and does not ask the API', async () => {
    const { completionRequests } = haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    renderApp()

    await screen.findByRole('heading', { level: 3, name: 'Renard de braise' })
    await screen.findByRole('link', { name: 'Se connecter' })

    expect(screen.queryByRole('region', { name: 'Progression' })).not.toBeInTheDocument()
    expect(screen.queryByText(/×/)).not.toBeInTheDocument()
    expect(completionRequests).toHaveLength(0)
  })

  it('lets a keyboard user reach the detail shown on hover', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    renderApp()

    const bar = within(await progress()).getByRole('progressbar')
    bar.focus()

    expect(bar).toHaveFocus()
  })

  it('follows what is added from a card page', async () => {
    signInAs()
    haveCollection()
    const { user } = renderApp()

    expect(await progress()).toHaveTextContent('0 possédée · 2 manquantes')

    await user.click(screen.getByRole('heading', { level: 3, name: 'Renard de braise' }))
    await user.click(await screen.findByRole('button', { name: 'Ajouter à ma collection' }))
    await screen.findByRole('group', { name: 'Quantité (Français)' })
    await user.click(screen.getByRole('link', { name: 'Retour au catalogue' }))

    await waitFor(() => expect(screen.getByRole('region', { name: 'Progression' })).toHaveTextContent('1 possédée · 1 manquante'))
    expect(tile('Renard de braise').getByText('FR ×1')).toBeInTheDocument()
  })

  it('rounds down, so that 100 % is only shown for a complete search', async () => {
    signInAs()
    completeWith({ total: 1000, owned: 999 })
    renderApp()

    const block = await progress()
    expect(block).toHaveTextContent('99 %')
    expect(block).toHaveTextContent('999 possédées · 1 manquante')
  })

  it('never shows 0 % to someone who owns a card', async () => {
    signInAs()
    completeWith({ total: 1000, owned: 1 })
    renderApp()

    expect(await progress()).toHaveTextContent('< 1 %')
  })

  it('shows 100 % when every card of the search is owned', async () => {
    signInAs()
    completeWith({ total: 12, owned: 12 })
    renderApp()

    const block = await progress()
    expect(block).toHaveTextContent('100 %')
    expect(block).toHaveTextContent('12 possédées · 0 manquante')
  })

  it('shows no progress for a search without result', async () => {
    signInAs()
    completeWith({ total: 0, owned: 0 })
    renderApp()

    await screen.findByRole('link', { name: 'Ma collection' })
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('2 cartes'))

    expect(screen.queryByRole('region', { name: 'Progression' })).not.toBeInTheDocument()
  })

  it('leaves the catalogue usable when the rate cannot be loaded', async () => {
    signInAs()
    server.use(
      http.get('*/api/collection/completion', () =>
        HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 }),
      ),
    )
    renderApp()

    expect(await screen.findByRole('heading', { level: 3, name: 'Renard de braise' })).toBeInTheDocument()
    await screen.findByRole('link', { name: 'Ma collection' })

    expect(screen.queryByRole('region', { name: 'Progression' })).not.toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
