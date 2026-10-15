import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import type { CardIdentitySummary } from '../../api/types'
import { cardPage, emberFox, foxIdentity, identityPage, mistOwl, owlIdentity } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

/** Answers with the given pages of missing identities, and keeps the requests it got. */
function haveMissingIdentities(...pages: CardIdentitySummary[][]) {
  const requests: URL[] = []
  server.use(
    http.get('*/api/collection/identities/missing', ({ request }) => {
      const url = new URL(request.url)
      requests.push(url)
      const page = Number(url.searchParams.get('page'))

      return HttpResponse.json(identityPage(pages[page - 1] ?? [], { page, totalPages: pages.length }))
    }),
  )

  return requests
}

function identities(count: number): CardIdentitySummary[] {
  return Array.from({ length: count }, (_, index) => ({
    ...owlIdentity,
    id: `identity-${index}`,
    name: `Créature ${index + 1}`,
    sortOrder: index + 1,
    imageUrl: null,
  }))
}

describe('placeholders to print', () => {
  it('lays the missing identities out nine to a sheet, reading every page of the API', async () => {
    signInAs()
    const requests = haveMissingIdentities(identities(10), [{ ...foxIdentity, sortOrder: 25 }])
    renderApp('/placeholders')

    const first = await screen.findByRole('list', { name: 'Feuille 1' })
    expect(within(first).getAllByRole('listitem')).toHaveLength(9)
    const second = screen.getByRole('list', { name: 'Feuille 2' })
    expect(within(second).getAllByRole('listitem')).toHaveLength(2)
    expect(within(second).getByText('Renard')).toBeInTheDocument()
    expect(within(second).getByText('0025')).toBeInTheDocument()
    expect(screen.getByText(/11 cartes sur 2 feuilles A4/)).toBeInTheDocument()
    expect(requests.map((url) => url.searchParams.get('page'))).toEqual(['1', '2'])
    expect(requests[0].searchParams.get('game')).toBe('demo')
  })

  it('prints on demand', async () => {
    signInAs()
    haveMissingIdentities([foxIdentity])
    const print = vi.spyOn(window, 'print').mockImplementation(() => {})
    renderApp('/placeholders')

    await screen.findByRole('list', { name: 'Feuille 1' })
    await userEvent.click(screen.getByRole('button', { name: 'Imprimer' }))

    expect(print).toHaveBeenCalledOnce()
  })

  it('prints every identity once told not to keep to what is missing', async () => {
    signInAs()
    const missing = haveMissingIdentities([foxIdentity])
    const owl = { ...owlIdentity, imageUrl: 'https://images.example.org/card.webp', identityImageUrl: 'https://images.example.org/owl.png' }
    server.use(http.get('*/api/identities', () => HttpResponse.json(identityPage([foxIdentity, owl]))))
    const { router, user } = renderApp('/placeholders')

    await screen.findByText('Renard')
    await user.click(screen.getByRole('checkbox', { name: 'Seulement ce qui me manque' }))

    expect(await screen.findByText(/2 cartes sur 1 feuille A4/)).toBeInTheDocument()
    // The picture of what is missing, not that of one of its cards.
    const sheet = screen.getByRole('list', { name: 'Feuille 1' })
    expect(within(sheet).getByRole('presentation')).toHaveAttribute('src', 'https://images.example.org/owl.png')
    expect(router.state.location.search).toBe('?game=demo&all=1')
    expect(missing).toHaveLength(1)
  })

  describe('one per card of a set', () => {
    /** Answers with the missing cards of a set, and keeps the requests it got. */
    function haveMissingCards() {
      const requests: URL[] = []
      server.use(
        http.get('*/api/collection/missing', ({ request }) => {
          requests.push(new URL(request.url))

          return HttpResponse.json(cardPage([mistOwl]))
        }),
      )

      return requests
    }

    it('asks for a set, then prints the cards of it the user misses', async () => {
      signInAs()
      haveMissingIdentities([foxIdentity])
      const requests = haveMissingCards()
      const { router, user } = renderApp('/placeholders')

      await user.click(await screen.findByRole('radio', { name: "Carte d'une extension" }))
      expect(await screen.findByText(/Choisissez une extension/)).toBeInTheDocument()
      expect(screen.queryByRole('list', { name: 'Feuille 1' })).not.toBeInTheDocument()

      await user.click(screen.getByRole('combobox', { name: 'Extension' }))
      await user.click(await screen.findByRole('option', { name: /Aube/ }))

      const sheet = await screen.findByRole('list', { name: 'Feuille 1' })
      expect(within(sheet).getByText(mistOwl.name)).toBeInTheDocument()
      expect(within(sheet).getByText(mistOwl.numberInSet)).toBeInTheDocument()
      expect(router.state.location.search).toBe('?mode=cards&game=demo&set=AUB')
      expect(requests[0].searchParams.get('set')).toBe('AUB')
    })

    it('prints every card of the set once told not to keep to what is missing', async () => {
      signInAs()
      const missing = haveMissingCards()
      server.use(http.get('*/api/cards', () => HttpResponse.json(cardPage([emberFox, mistOwl]))))
      renderApp('/placeholders?mode=cards&game=demo&set=AUB&all=1')

      const sheet = await screen.findByRole('list', { name: 'Feuille 1' })
      expect(within(sheet).getAllByRole('listitem')).toHaveLength(2)
      expect(missing).toHaveLength(0)
    })
  })

  it('says so when no identity is missing', async () => {
    signInAs()
    haveMissingIdentities([])
    renderApp('/placeholders')

    expect(await screen.findByRole('heading', { name: 'Rien à imprimer' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Imprimer' })).toBeDisabled()
  })
})
