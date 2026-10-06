import { screen, within } from '@testing-library/react'
import type { UserEvent } from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { getAccessToken } from '../../api/accessToken'
import { demoPassword } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { allowRegistration, signInAs } from '../../test/session'

const NEW_EMAIL = 'nouveau@example.com'

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

async function submitRegistration(user: UserEvent, email: string, password: string) {
  if (email !== '') await user.type(emailField(), email)
  if (password !== '') await user.type(passwordField(), password)
  await user.click(screen.getByRole('button', { name: 'Créer mon compte' }))
}

function refuseRegistration(status: number, body: object) {
  const requests: unknown[] = []
  server.use(
    http.post('*/api/auth/register', async ({ request }) => {
      requests.push(await request.json())

      return HttpResponse.json(body, { status })
    }),
  )

  return requests
}

describe('RegisterPage', () => {
  it('is reached from the sign-in screen, and leads back to it', async () => {
    const { router, user } = renderApp('/login')

    await user.click(screen.getByRole('link', { name: 'Créer un compte' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Créer un compte' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/register')
    expect(document.title).toBe('Créer un compte — Objectif Binder')

    // The header has a link of the same name: take the one under the form.
    await user.click(within(screen.getByRole('main')).getByRole('link', { name: 'Se connecter' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Connexion' })).toBeInTheDocument()
  })

  it('creates the account, signs in with it and leads to the catalogue', async () => {
    const { registrations, logins } = allowRegistration()
    const { router, user } = renderApp('/register')

    await submitRegistration(user, NEW_EMAIL, demoPassword)

    expect(await header().findByText(NEW_EMAIL)).toBeInTheDocument()
    expect(await screen.findByRole('heading', { level: 1, name: 'Catalogue' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
    // The password is typed once and sent to both routes.
    expect(registrations).toEqual([{ email: NEW_EMAIL, password: demoPassword }])
    expect(logins).toEqual([{ email: NEW_EMAIL, password: demoPassword }])
    expect(getAccessToken()).not.toBeNull()
  })

  it('brings a new user back to the page they started from, through both screens', async () => {
    allowRegistration()
    const { router, user } = renderApp('/cards/card-1')
    await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })

    await user.click(header().getByRole('link', { name: 'Se connecter' }))
    await user.click(await screen.findByRole('link', { name: 'Créer un compte' }))
    await submitRegistration(user, NEW_EMAIL, demoPassword)

    expect(await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/cards/card-1')
  })

  it('refuses a password that is too short before calling the API', async () => {
    const { registrations } = allowRegistration()
    const { user } = renderApp('/register')

    await submitRegistration(user, NEW_EMAIL, 'neuf lett')

    expect(passwordField()).toBeInvalid()
    expect(passwordField()).toHaveAccessibleDescription(/au moins 10 caractères/)
    expect(passwordField()).toHaveFocus()
    expect(registrations).toHaveLength(0)
  })

  it('asks for the missing fields, focusing the first one', async () => {
    const { registrations } = allowRegistration()
    const { user } = renderApp('/register')

    await submitRegistration(user, '', '')

    expect(emailField()).toHaveAccessibleDescription('Saisissez votre adresse e-mail.')
    expect(emailField()).toHaveFocus()
    expect(passwordField()).toBeInvalid()
    expect(registrations).toHaveLength(0)
  })

  it('states the password rule next to the field, before any mistake', () => {
    renderApp('/register')

    expect(passwordField()).toHaveAccessibleDescription(/10 caractères minimum/)
    expect(passwordField()).not.toHaveAttribute('aria-invalid')
    expect(passwordField()).toHaveAttribute('autocomplete', 'new-password')
  })

  it('shows what the API refused under the field at fault, in French', async () => {
    refuseRegistration(422, {
      error: 'Validation failed.',
      violations: {
        email: ['This value is not a valid email address.'],
        password: ['The password strength is too low. Please use a stronger password.'],
      },
    })
    const { router, user } = renderApp('/register')

    await submitRegistration(user, 'pas-une-adresse', 'aaaaaaaaaaaa')

    expect(await screen.findByText('Saisissez une adresse e-mail valide.')).toBeInTheDocument()
    expect(emailField()).toBeInvalid()
    expect(emailField()).toHaveFocus()
    expect(passwordField()).toHaveAccessibleDescription(/plus long ou moins prévisible/)
    // The API's English wording never reaches the page.
    expect(screen.queryByText(/password strength/i)).not.toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/register')
  })

  it('says when the e-mail already has an account', async () => {
    refuseRegistration(409, { error: 'This e-mail address is already registered.' })
    const { user } = renderApp('/register')

    await submitRegistration(user, NEW_EMAIL, demoPassword)

    expect(await screen.findByText('Un compte existe déjà avec cette adresse e-mail.')).toBeInTheDocument()
    expect(emailField()).toBeInvalid()
    expect(getAccessToken()).toBeNull()
  })

  it('clears a refusal once the form is corrected and accepted', async () => {
    refuseRegistration(409, { error: 'This e-mail address is already registered.' })
    const { user } = renderApp('/register')
    await submitRegistration(user, NEW_EMAIL, demoPassword)
    await screen.findByText('Un compte existe déjà avec cette adresse e-mail.')

    allowRegistration()
    await user.clear(emailField())
    await user.type(emailField(), 'autre@example.com')
    await user.click(screen.getByRole('button', { name: 'Créer mon compte' }))

    expect(await header().findByText('autre@example.com')).toBeInTheDocument()
  })

  it('explains the limit when too many accounts were created', async () => {
    refuseRegistration(429, { error: 'Too Many Requests' })
    const { user } = renderApp('/register')

    await submitRegistration(user, NEW_EMAIL, demoPassword)

    expect(await screen.findByRole('alert')).toHaveTextContent('Trop de comptes ont été créés')
  })

  it('says so when the API cannot be reached', async () => {
    refuseRegistration(500, { error: 'Internal Server Error' })
    const { user } = renderApp('/register')

    await submitRegistration(user, NEW_EMAIL, demoPassword)

    expect(await screen.findByRole('alert')).toHaveTextContent('Inscription impossible pour le moment')
  })

  it('sends to the sign-in screen when the account is created but signing in fails', async () => {
    allowRegistration()
    server.use(http.post('*/api/auth/login', () => HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 })))
    const { router, user } = renderApp('/register')

    await submitRegistration(user, NEW_EMAIL, demoPassword)

    // Not an error on the sign-up form: retrying there would answer "already registered".
    expect(await screen.findByRole('heading', { level: 1, name: 'Connexion' })).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Votre compte est créé')
    expect(router.state.location.pathname).toBe('/login')
  })

  it('sends a user who is already signed in away from the form', async () => {
    signInAs()
    const { router } = renderApp('/register')

    expect(await screen.findByRole('heading', { level: 1, name: 'Catalogue' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
  })
})
