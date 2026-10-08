import { screen, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { haveCollection, ownedCard } from '../../test/collection'
import { cardPage, demoCards, emberFox, mistOwl } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

function cardNames() {
  return screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)
}

/** Counts the calls to the public catalogue route. */
function watchCatalogue() {
  const requests: URL[] = []
  server.use(
    http.get('*/api/cards', ({ request }) => {
      requests.push(new URL(request.url))

      return HttpResponse.json(cardPage(demoCards))
    }),
  )

  return requests
}

describe('ownership filter of the catalogue', () => {
  it('is offered to a signed-in user only', async () => {
    renderApp()
    await screen.findByRole('link', { name: 'Se connecter' })

    expect(screen.queryByRole('combobox', { name: 'Possession' })).not.toBeInTheDocument()
  })

  it('narrows the catalogue to the missing cards', async () => {
    signInAs()
    const { missingRequests } = haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    const { router, user } = renderApp('/?game=demo')

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Possession' }), 'Manquantes')

    await waitFor(() => expect(cardNames()).toEqual([mistOwl.name]))
    expect(router.state.location.search).toBe('?game=demo&ownership=missing')
    // The other filters go along; the choice itself is the route, not a parameter.
    expect(Object.fromEntries(missingRequests[0].searchParams)).toEqual({ game: 'demo', page: '1', limit: '20' })
    expect(screen.getByRole('status')).toHaveTextContent('1 carte')
    expect(screen.queryByText('Possédée')).not.toBeInTheDocument()
  })

  it('narrows the catalogue to the owned cards, each marked as owned', async () => {
    signInAs()
    const { listRequests } = haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    const { user } = renderApp()

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Possession' }), 'Possédées')

    await waitFor(() => expect(cardNames()).toEqual([emberFox.name]))
    expect(listRequests).toHaveLength(1)
    expect(screen.getByText('Possédée')).toBeInTheDocument()
  })

  it('keeps the progress on the whole search, whichever side of it is shown', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    renderApp('/?ownership=missing')

    await waitFor(() => expect(cardNames()).toEqual([mistOwl.name]))

    expect(await screen.findByRole('region', { name: 'Progression' })).toHaveTextContent('50 %')
  })

  it('does not show every card for an instant when a filtered page is reloaded', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    const catalogueRequests = watchCatalogue()
    renderApp('/?ownership=missing')

    await waitFor(() => expect(cardNames()).toEqual([mistOwl.name]))

    expect(screen.getByRole('combobox', { name: 'Possession' })).toHaveValue('missing')
    expect(catalogueRequests).toHaveLength(0)
  })

  it('ignores the filter for a visitor, who gets the whole catalogue', async () => {
    const { missingRequests } = haveCollection()
    renderApp('/?ownership=missing')

    await screen.findByRole('link', { name: 'Se connecter' })
    await waitFor(() => expect(cardNames()).toEqual([emberFox.name, mistOwl.name]))

    expect(missingRequests).toHaveLength(0)
  })

  it('says so when nothing is missing', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)], [mistOwl.id]: [ownedCard('en', 1)] })
    renderApp('/?ownership=missing')

    expect(await screen.findByRole('heading', { name: 'Il ne vous manque aucune carte ici' })).toBeInTheDocument()
    expect(screen.getByText('Vous possédez toutes les cartes de cette recherche.')).toBeInTheDocument()
  })

  it('is cleared with the other filters', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    const { router, user } = renderApp('/?ownership=missing')

    await waitFor(() => expect(cardNames()).toEqual([mistOwl.name]))
    await user.click(screen.getByRole('button', { name: 'Réinitialiser les filtres' }))

    await waitFor(() => expect(cardNames()).toEqual([emberFox.name, mistOwl.name]))
    expect(router.state.location.search).toBe('')
    expect(screen.getByRole('combobox', { name: 'Possession' })).toHaveValue('')
  })

  it('drops a card from the missing ones once it is added from its page', async () => {
    signInAs()
    haveCollection()
    const { user } = renderApp('/?ownership=missing')

    await user.click(await screen.findByRole('heading', { level: 3, name: emberFox.name }))
    await user.click(await screen.findByRole('button', { name: 'Ajouter à ma collection' }))
    await screen.findByRole('group', { name: 'Quantité (Français)' })
    await user.click(screen.getByRole('link', { name: 'Retour au catalogue' }))

    await waitFor(() => expect(cardNames()).toEqual([mistOwl.name]))
  })
})
