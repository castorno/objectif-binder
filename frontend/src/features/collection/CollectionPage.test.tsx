import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { haveCollection, ownedCard } from '../../test/collection'
import { emberFox, mistOwl } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { signInAs } from '../../test/session'

function header() {
  return within(screen.getAllByRole('banner')[0])
}

describe('collection page (signed-in users only)', () => {
  it('lists each owned card once, with what is owned in each language', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 2), ownedCard('ja', 1)], [mistOwl.id]: [ownedCard('en', 1)] })
    renderApp('/collection')

    expect(await screen.findByRole('heading', { level: 1, name: 'Ma collection' })).toBeInTheDocument()
    const fox = (await screen.findByRole('heading', { level: 3, name: 'Renard de braise' })).closest('li')!

    expect(screen.getAllByRole('heading', { level: 3 })).toHaveLength(2)
    expect(within(fox).getByText('Français : 2 exemplaires')).toBeInTheDocument()
    expect(within(fox).getByText('Japonais : 1 exemplaire')).toBeInTheDocument()
    expect(within(fox).getByText('FR ×2')).toBeInTheDocument()
    expect(screen.getByText('2 cartes')).toBeInTheDocument()
    expect(document.title).toBe('Ma collection — Objectif Binder')
  })

  it('points an empty collection to the catalogue', async () => {
    signInAs()
    renderApp('/collection')

    expect(await screen.findByRole('heading', { name: 'Votre collection est vide' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Parcourir le catalogue' })).toHaveAttribute('href', '/')
  })

  it('offers to reset the filters when none of the owned cards match', async () => {
    signInAs()
    renderApp('/collection?q=dragon')

    expect(
      await screen.findByRole('heading', { name: 'Aucune carte de votre collection ne correspond' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('main')).toContainElement(screen.getAllByRole('button', { name: 'Réinitialiser les filtres' })[0])
    expect(screen.queryByRole('link', { name: 'Parcourir le catalogue' })).not.toBeInTheDocument()
  })

  it('sends the filters of the URL to the API', async () => {
    signInAs()
    const { listRequests } = haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    renderApp('/collection?q=renard&game=demo&page=2')

    await screen.findByRole('heading', { level: 1, name: 'Ma collection' })
    await screen.findByText('1 carte')

    const params = listRequests[0].searchParams
    expect(params.get('q')).toBe('renard')
    expect(params.get('game')).toBe('demo')
    expect(params.get('page')).toBe('2')
  })

  it('sends a visitor to the sign-in screen', async () => {
    const { router } = renderApp('/collection')

    expect(await screen.findByRole('heading', { level: 1, name: 'Connexion' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(router.state.location.state).toEqual({ from: '/collection' })
  })

  it('is reached from the header, which only shows the link to a signed-in user', async () => {
    signInAs()
    const { router, user } = renderApp()

    await user.click(await header().findByRole('link', { name: 'Ma collection' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Ma collection' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/collection')
  })

  it('does not show the header link to a visitor', async () => {
    renderApp()

    await header().findByRole('link', { name: 'Se connecter' })

    expect(header().queryByRole('link', { name: 'Ma collection' })).not.toBeInTheDocument()
  })

  it('opens a card and comes back to the collection with the same filters', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    const { router, user } = renderApp('/collection?q=renard')

    await user.click(await screen.findByRole('heading', { level: 3, name: 'Renard de braise' }))
    await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })
    await user.click(screen.getByRole('link', { name: 'Retour à ma collection' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Ma collection' })).toBeInTheDocument()
    expect(router.state.location.pathname + router.state.location.search).toBe('/collection?q=renard')
  })

  it('shows a card removed from its page as gone when coming back', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    const { user } = renderApp('/collection')

    await user.click(await screen.findByRole('heading', { level: 3, name: 'Renard de braise' }))
    await user.click(await screen.findByRole('button', { name: 'Retirer Français de ma collection' }))
    await screen.findByText('Vous ne possédez pas encore cette carte.')
    await user.click(screen.getByRole('link', { name: 'Retour à ma collection' }))

    expect(await screen.findByRole('heading', { name: 'Votre collection est vide' })).toBeInTheDocument()
  })
})
