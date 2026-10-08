import { screen, waitFor, within } from '@testing-library/react'
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
    expect(screen.queryByText(/×/)).not.toBeInTheDocument()
  })

  it('narrows the catalogue to the owned cards, with what is owned of each', async () => {
    signInAs()
    const { listRequests } = haveCollection({ [emberFox.id]: [ownedCard('fr', 2), ownedCard('ja', 1)] })
    const { user } = renderApp()

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Possession' }), 'Possédées')

    await waitFor(() => expect(cardNames()).toEqual([emberFox.name]))
    expect(listRequests).toHaveLength(1)
    expect(screen.getByText('FR ×2')).toBeInTheDocument()
    expect(screen.getByText('JA ×1')).toBeInTheDocument()
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

  it('is what "Ma collection" in the header leads to', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    const { router, user } = renderApp()
    const link = await within(screen.getAllByRole('banner')[0]).findByRole('link', { name: 'Ma collection' })
    expect(link).not.toHaveAttribute('aria-current')

    await user.click(link)

    await waitFor(() => expect(cardNames()).toEqual([emberFox.name]))
    expect(router.state.location.pathname + router.state.location.search).toBe('/?ownership=owned')
    expect(screen.getByRole('combobox', { name: 'Possession' })).toHaveValue('owned')
    expect(link).toHaveAttribute('aria-current', 'page')
  })

  it('does not show "Ma collection" to a visitor', async () => {
    renderApp()
    const header = within(screen.getAllByRole('banner')[0])

    await header.findByRole('link', { name: 'Se connecter' })

    expect(header.queryByRole('link', { name: 'Ma collection' })).not.toBeInTheDocument()
  })

  it('tells an empty collection apart from a search without result', async () => {
    signInAs()
    haveCollection()
    const { router, user } = renderApp('/?ownership=owned')

    expect(await screen.findByRole('heading', { name: 'Votre collection est vide' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Voir toutes les cartes' }))

    await waitFor(() => expect(cardNames()).toEqual([emberFox.name, mistOwl.name]))
    expect(router.state.location.search).toBe('')
  })

  it('says that no owned card matches when other filters are set', async () => {
    signInAs()
    haveCollection()
    renderApp('/?ownership=owned&q=dragon')

    expect(await screen.findByRole('heading', { name: 'Aucune carte ne correspond à cette recherche' })).toBeInTheDocument()
  })

  it('no longer has a page of its own', async () => {
    signInAs()
    renderApp('/collection')

    expect(await screen.findByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument()
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
