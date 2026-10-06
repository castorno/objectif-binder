import { screen, within } from '@testing-library/react'
import type { UserEvent } from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { getAccessToken } from '../../api/accessToken'
import { demoPassword, demoUser } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { allowLogin, signInAs } from '../../test/session'

function header() {
  // The site header comes first; a card page has a <header> of its own, which
  // the testing library also reports as a banner.
  return within(screen.getAllByRole('banner')[0])
}

function emailField() {
  return screen.getByRole('textbox', { name: 'Adresse e-mail' })
}

function passwordField() {
  // A password input has no ARIA role: it is found by its label.
  return screen.getByLabelText('Mot de passe')
}

async function submitCredentials(user: UserEvent, email: string, password: string) {
  await user.type(emailField(), email)
  await user.type(passwordField(), password)
  await user.click(screen.getByRole('button', { name: 'Se connecter' }))
}

describe('LoginPage', () => {
  it('is reached from the header by a visitor', async () => {
    const { router, user } = renderApp()

    await user.click(await header().findByRole('link', { name: 'Se connecter' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Connexion' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(document.title).toBe('Connexion — Objectif Binder')
  })

  it('signs the user in and leads to the catalogue', async () => {
    const attempts = allowLogin()
    const { router, user } = renderApp('/login')

    await submitCredentials(user, demoUser.email, demoPassword)

    expect(await header().findByText(demoUser.email)).toBeInTheDocument()
    expect(await screen.findByRole('heading', { level: 1, name: 'Catalogue' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
    expect(attempts).toEqual([{ email: demoUser.email, password: demoPassword }])
    expect(getAccessToken()).not.toBeNull()
  })

  it('brings the user back to the page they came from', async () => {
    allowLogin()
    const { router, user } = renderApp('/cards/card-1')
    await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })

    await user.click(header().getByRole('link', { name: 'Se connecter' }))
    await submitCredentials(user, demoUser.email, demoPassword)

    expect(await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/cards/card-1')
  })

  it('does not keep the sign-in page in the history once signed in', async () => {
    allowLogin()
    const { router, user } = renderApp('/cards/card-1')
    await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })
    await user.click(header().getByRole('link', { name: 'Se connecter' }))
    await submitCredentials(user, demoUser.email, demoPassword)
    await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })

    await router.navigate(-1)

    // "Back" must not return to a sign-in form that would bounce forward again.
    expect(router.state.location.pathname).not.toBe('/login')
  })

  it('ignores spaces typed around the e-mail', async () => {
    const attempts = allowLogin()
    const { user } = renderApp('/login')

    await submitCredentials(user, `  ${demoUser.email} `, demoPassword)

    expect(await header().findByText(demoUser.email)).toBeInTheDocument()
    expect(attempts).toEqual([{ email: demoUser.email, password: demoPassword }])
  })

  it('reports wrong credentials without saying which one is wrong', async () => {
    allowLogin()
    const { router, user } = renderApp('/login')

    await submitCredentials(user, demoUser.email, 'not the password')

    expect(await screen.findByRole('alert')).toHaveTextContent('E-mail ou mot de passe incorrect.')
    expect(router.state.location.pathname).toBe('/login')
    expect(getAccessToken()).toBeNull()
    // What was typed is still there to be corrected.
    expect(emailField()).toHaveValue(demoUser.email)
  })

  it('explains the wait when there were too many attempts', async () => {
    server.use(
      http.post('*/api/auth/login', () =>
        HttpResponse.json({ error: 'Too many login attempts. Try again later.' }, { status: 429 }),
      ),
    )
    const { user } = renderApp('/login')

    await submitCredentials(user, demoUser.email, demoPassword)

    expect(await screen.findByRole('alert')).toHaveTextContent('Trop de tentatives')
  })

  it('says so when the API cannot be reached, without blaming the credentials', async () => {
    server.use(http.post('*/api/auth/login', () => HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 })))
    const { user } = renderApp('/login')

    await submitCredentials(user, demoUser.email, demoPassword)

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Connexion impossible pour le moment')
    expect(alert).not.toHaveTextContent('incorrect')
  })

  it('asks for the missing fields instead of calling the API', async () => {
    const attempts = allowLogin()
    const { user } = renderApp('/login')

    await user.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(emailField()).toBeInvalid()
    expect(emailField()).toHaveAccessibleDescription('Saisissez votre adresse e-mail.')
    expect(passwordField()).toHaveAccessibleDescription('Saisissez votre mot de passe.')
    expect(emailField()).toHaveFocus()
    expect(attempts).toHaveLength(0)
  })

  it('lets the user reveal the password, and hide it again', async () => {
    const { user } = renderApp('/login')
    const toggle = screen.getByRole('button', { name: 'Afficher le mot de passe' })
    expect(passwordField()).toHaveAttribute('type', 'password')

    await user.click(toggle)
    expect(passwordField()).toHaveAttribute('type', 'text')
    expect(toggle).toHaveAttribute('aria-pressed', 'true')

    await user.click(toggle)
    expect(passwordField()).toHaveAttribute('type', 'password')
  })

  it('helps password managers recognise the fields', () => {
    renderApp('/login')

    expect(emailField()).toHaveAttribute('autocomplete', 'email')
    expect(passwordField()).toHaveAttribute('autocomplete', 'current-password')
  })

  it('sends a user who is already signed in away from the form', async () => {
    signInAs()
    const { router } = renderApp('/login')

    expect(await screen.findByRole('heading', { level: 1, name: 'Catalogue' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
  })
})
