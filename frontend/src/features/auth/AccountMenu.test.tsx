import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { getAccessToken } from '../../api/accessToken'
import { demoUser } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

function header() {
  return within(screen.getByRole('banner'))
}

async function catalogueIsLoaded() {
  await screen.findByRole('heading', { name: 'Renard de braise' })
}

describe('AccountMenu', () => {
  it('shows nothing about an account to a visitor', async () => {
    renderApp()
    await catalogueIsLoaded()

    expect(header().queryByRole('button', { name: 'Se déconnecter' })).not.toBeInTheDocument()
    expect(header().queryByText(demoUser.email)).not.toBeInTheDocument()
  })

  it('restores the session when the application starts, as after a page reload', async () => {
    signInAs()
    renderApp()

    expect(await header().findByText(demoUser.email)).toBeInTheDocument()
    expect(header().getByRole('button', { name: 'Se déconnecter' })).toBeInTheDocument()
    expect(getAccessToken()).not.toBeNull()
  })

  it('signs out through the API, then forgets the user and the token', async () => {
    signInAs()
    const logoutRequests: Request[] = []
    server.use(
      http.post('*/api/auth/logout', ({ request }) => {
        logoutRequests.push(request)

        return new HttpResponse(null, { status: 204 })
      }),
    )
    const { user } = renderApp()

    await user.click(await header().findByRole('button', { name: 'Se déconnecter' }))

    await waitFor(() => expect(header().queryByText(demoUser.email)).not.toBeInTheDocument())
    expect(header().queryByRole('button', { name: 'Se déconnecter' })).not.toBeInTheDocument()
    expect(getAccessToken()).toBeNull()
    expect(logoutRequests).toHaveLength(1)
    // The cookie identifies the session; an expired token would get the request refused.
    expect(logoutRequests[0].headers.has('Authorization')).toBe(false)
  })

  it('stays signed in and says so when signing out fails', async () => {
    signInAs()
    server.use(http.post('*/api/auth/logout', () => HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 })))
    const { user } = renderApp()

    await user.click(await header().findByRole('button', { name: 'Se déconnecter' }))

    // Pretending to be signed out while the session cookie still works would be misleading.
    expect(await header().findByRole('alert')).toHaveTextContent('Déconnexion impossible')
    expect(header().getByText(demoUser.email)).toBeInTheDocument()
    expect(getAccessToken()).not.toBeNull()
  })

  it('leaves the catalogue usable when the session cannot be checked', async () => {
    server.use(http.post('*/api/auth/refresh', () => HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 })))
    renderApp()

    await catalogueIsLoaded()

    expect(header().queryByRole('button', { name: 'Se déconnecter' })).not.toBeInTheDocument()
  })
})
