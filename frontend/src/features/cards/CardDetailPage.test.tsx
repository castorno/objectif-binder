import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { emberFox, mistOwl } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'

describe('CardDetailPage', () => {
  it('announces the loading, then shows the card', async () => {
    renderApp(`/cards/${emberFox.id}`)

    expect(screen.getByRole('status')).toHaveTextContent('Chargement de la carte…')

    expect(await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })).toBeInTheDocument()
    expect(screen.getByText('Aube (AUB) · n° 012')).toBeInTheDocument()
    expect(screen.getByText(/1 chance sur 100/)).toBeInTheDocument()
    expect(document.title).toBe('Renard de braise — Objectif Binder')
  })

  it('lists the attributes, joining multiple values', async () => {
    renderApp(`/cards/${emberFox.id}`)

    const attributes = within(await screen.findByRole('region', { name: 'Caractéristiques' }))

    expect(attributes.getAllByRole('term').map((term) => term.textContent)).toEqual(['type', 'attaques'])
    expect(attributes.getByText('Feu')).toBeInTheDocument()
    expect(attributes.getByText('Griffe, Flammèche')).toBeInTheDocument()
  })

  it('leaves out the attributes section for a card without any', async () => {
    renderApp(`/cards/${mistOwl.id}`)

    await screen.findByRole('heading', { level: 1, name: 'Chouette des brumes' })

    expect(screen.queryByRole('region', { name: 'Caractéristiques' })).not.toBeInTheDocument()
    expect(screen.getByText(/n'est pas renseigné pour l'extension Crépuscule/)).toBeInTheDocument()
  })

  it('explains that an unknown card does not exist, without offering to retry', async () => {
    renderApp('/cards/unknown')

    expect(await screen.findByRole('heading', { name: 'Carte introuvable' })).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Réessayer' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Retour au catalogue' })).toHaveAttribute('href', '/')
    expect(document.title).toBe('Carte introuvable — Objectif Binder')
  })

  it('reports a server error and recovers when retrying', async () => {
    let apiIsDown = true
    server.use(
      http.get('*/api/cards/:id', () =>
        apiIsDown ? HttpResponse.json({ error: 'Internal error.' }, { status: 500 }) : HttpResponse.json(emberFox),
      ),
    )
    const { user } = renderApp(`/cards/${emberFox.id}`)

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Impossible de charger la carte')

    apiIsDown = false
    await user.click(within(alert).getByRole('button', { name: 'Réessayer' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })).toBeInTheDocument()
  })

  it('links back to the catalogue when opened directly', async () => {
    renderApp(`/cards/${emberFox.id}`)

    await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })

    expect(screen.getByRole('link', { name: 'Retour au catalogue' })).toHaveAttribute('href', '/')
  })
})
