import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { demoPassword, demoUser } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { allowLogin, signInAs } from '../../test/session'

function header() {
  return within(screen.getAllByRole('banner')[0])
}

function main() {
  return within(screen.getByRole('main'))
}

describe('account page (signed-in users only)', () => {
  it('shows the account of the signed-in user', async () => {
    signInAs()
    renderApp('/account')

    expect(await screen.findByRole('heading', { level: 1, name: 'Mon compte' })).toBeInTheDocument()
    expect(main().getByText(demoUser.email)).toBeInTheDocument()
    expect(document.title).toBe('Mon compte — Objectif Binder')
  })

  it('saves the condition a card added starts with, as soon as it is chosen', async () => {
    signInAs()
    const bodies: unknown[] = []
    server.use(
      http.put('*/api/me/preferences', async ({ request }) => {
        const body = (await request.json()) as { defaultCondition: string | null }
        bodies.push(body)

        return HttpResponse.json({ ...demoUser, ...body })
      }),
    )
    const { user } = renderApp('/account')

    const select = await screen.findByRole('combobox', { name: "État d'une carte ajoutée" })
    expect(select).toHaveValue('')
    await user.selectOptions(select, 'Near Mint')

    expect(await main().findByText('Préférence enregistrée.')).toBeInTheDocument()
    expect(bodies).toEqual([{ defaultCondition: 'near_mint' }])
    // Shown from the answer of the API, not from what was clicked.
    expect(select).toHaveValue('near_mint')

    await user.selectOptions(select, 'Aucun')
    await waitFor(() => expect(bodies).toEqual([{ defaultCondition: 'near_mint' }, { defaultCondition: null }]))
  })

  it('says so when a preference cannot be saved', async () => {
    signInAs()
    server.use(http.put('*/api/me/preferences', () => HttpResponse.json({ error: 'Server error.' }, { status: 500 })))
    const { user } = renderApp('/account')

    await user.selectOptions(await screen.findByRole('combobox', { name: "État d'une carte ajoutée" }), 'Played')

    expect(await main().findByRole('alert')).toHaveTextContent('Enregistrement impossible. Réessayez.')
  })

  it('sums up the collection over the whole catalogue', async () => {
    signInAs()
    server.use(http.get('*/api/collection/completion', () => HttpResponse.json({ total: 20030, owned: 1234, ownedOnPage: {} })))
    renderApp('/account')

    const summary = within(await screen.findByRole('region', { name: 'Ma collection' }))
    expect(summary.getByText('1 234', { normalizer: (text) => text.replace(/\s/g, ' ') })).toBeInTheDocument()
    expect(summary.getByText(/cartes sur/)).toBeInTheDocument()
    expect(main().getByRole('link', { name: 'Voir ma collection' })).toHaveAttribute('href', '/?ownership=owned')
    expect(main().getByRole('link', { name: 'Quel booster ouvrir ?' })).toHaveAttribute('href', '/boosters')
  })

  it('is reached from the header once signed in', async () => {
    signInAs()
    const { router, user } = renderApp()

    await user.click(await header().findByRole('link', { name: `Mon compte (${demoUser.email})` }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Mon compte' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/account')
  })

  it('sends a visitor to the sign-in screen, then back to the page once signed in', async () => {
    allowLogin()
    const { router, user } = renderApp('/account')

    expect(await screen.findByRole('heading', { level: 1, name: 'Connexion' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(screen.queryByRole('heading', { name: 'Mon compte' })).not.toBeInTheDocument()

    await user.type(screen.getByRole('textbox', { name: 'Adresse e-mail' }), demoUser.email)
    await user.type(screen.getByLabelText('Mot de passe'), demoPassword)
    await user.click(main().getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Mon compte' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/account')
  })

  it('waits for the session to be restored instead of bouncing a signed-in user to the sign-in screen', async () => {
    signInAs()
    const visited: string[] = []
    const { router } = renderApp('/account')
    router.subscribe((state) => visited.push(state.location.pathname))

    expect(screen.getByRole('status')).toHaveTextContent('Chargement')

    expect(await screen.findByRole('heading', { level: 1, name: 'Mon compte' })).toBeInTheDocument()
    expect(visited).not.toContain('/login')
  })

  it('leaves for the sign-in screen when the user signs out', async () => {
    signInAs()
    const { router, user } = renderApp('/account')

    await user.click(await main().findByRole('button', { name: 'Se déconnecter' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Connexion' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(screen.queryByText(demoUser.email)).not.toBeInTheDocument()
  })

  it('stays on the page and says so when signing out fails', async () => {
    signInAs()
    server.use(http.post('*/api/auth/logout', () => HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 })))
    const { router, user } = renderApp('/account')

    await user.click(await main().findByRole('button', { name: 'Se déconnecter' }))

    expect(await main().findByRole('alert')).toHaveTextContent('Déconnexion impossible')
    expect(router.state.location.pathname).toBe('/account')
  })
})
