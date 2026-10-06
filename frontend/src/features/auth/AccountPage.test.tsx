import { screen, within } from '@testing-library/react'
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
