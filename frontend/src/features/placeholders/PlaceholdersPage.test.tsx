import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import type { CardIdentitySummary } from '../../api/types'
import { foxIdentity, identityPage, owlIdentity } from '../../test/fixtures'
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

  it('says so when no identity is missing', async () => {
    signInAs()
    haveMissingIdentities([])
    renderApp('/placeholders')

    expect(await screen.findByRole('heading', { name: 'Rien à imprimer' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Imprimer' })).toBeDisabled()
  })
})
