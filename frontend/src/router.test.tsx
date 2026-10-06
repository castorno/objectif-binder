import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { renderApp } from './test/render'

describe('routes', () => {
  it('goes from a search to a card and back to the same search', async () => {
    const { router, user } = renderApp('/?q=renard&game=demo')

    await user.click(await screen.findByRole('heading', { level: 3, name: 'Renard de braise' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Renard de braise' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/cards/card-1')

    await user.click(screen.getByRole('link', { name: 'Retour au catalogue' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Catalogue' })).toBeInTheDocument()
    expect(router.state.location.search).toBe('?q=renard&game=demo')
    expect(screen.getByRole('searchbox', { name: 'Nom de la carte' })).toHaveValue('renard')
    expect(screen.getByRole('combobox', { name: 'Jeu' })).toHaveValue('demo')
  })

  it('shows a not-found page for an unknown address', async () => {
    renderApp('/nowhere')

    expect(await screen.findByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Retour au catalogue' })).toHaveAttribute('href', '/')
    expect(document.title).toBe('Page introuvable — Objectif Binder')
  })

  it('keeps the site header and the skip link on every page', async () => {
    renderApp('/nowhere')

    expect(screen.getByRole('link', { name: 'Aller au contenu' })).toHaveAttribute('href', '#main')
    expect(screen.getByRole('banner')).toBeInTheDocument()
    expect(screen.getByRole('main')).toHaveAttribute('id', 'main')
  })
})
