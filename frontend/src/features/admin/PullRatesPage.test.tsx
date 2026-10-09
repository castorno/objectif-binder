import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { PullRate, SetPullRates } from '../../api/types'
import { demoUser } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

const admin = { ...demoUser, isAdmin: true }

const RATES: SetPullRates = {
  set: { id: 'set-1', name: 'Aube', code: 'AUB' },
  source: 'Ouverture de 1 000 boosters',
  updatedAt: '2026-10-08T09:00:00+00:00',
  rarities: [
    { id: 'rarity-common', name: 'Commune', cardsInSet: 40, rate: { cards: 4, boosters: 1 } },
    { id: 'rarity-rare', name: 'Rare', cardsInSet: 12, rate: { cards: 1, boosters: 6 } },
    { id: 'rarity-gold', name: 'Or', cardsInSet: 3, rate: null },
  ],
}

/** Serves the rates of a set, and applies what the page saves. Returns the bodies it received. */
function havePullRates(rates: SetPullRates = RATES) {
  const saved: unknown[] = []
  let current = rates

  server.use(
    http.get('*/api/admin/sets/:id/pull-rates', () => HttpResponse.json(current)),
    http.put('*/api/admin/sets/:id/pull-rates', async ({ request }) => {
      const body = (await request.json()) as { rates: Record<string, PullRate>; source: string | null }
      saved.push(body)
      current = {
        ...current,
        source: body.source,
        rarities: current.rarities.map((rarity) => ({ ...rarity, rate: body.rates[rarity.id] ?? null })),
      }

      return HttpResponse.json(current)
    }),
  )

  return saved
}

function cardsField(rarity: string) {
  return screen.findByRole('textbox', { name: `${rarity} : nombre de cartes` })
}

function boostersField(rarity: string) {
  return screen.findByRole('textbox', { name: `${rarity} : nombre de boosters` })
}

describe('pull rates administration', () => {
  it('is reached from the header by an administrator only', async () => {
    signInAs(admin)
    const { user } = renderApp('/')

    await user.click(await screen.findByRole('link', { name: 'Administration' }))

    expect(await screen.findByRole('heading', { name: 'Taux de drop' })).toBeInTheDocument()
  })

  it('shows a plain user neither the link nor the page', async () => {
    signInAs()
    renderApp('/admin/pull-rates')

    expect(await screen.findByRole('heading', { name: 'Page réservée aux administrateurs' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Administration' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Taux de drop' })).not.toBeInTheDocument()
  })

  it('sends a visitor to the sign-in screen', async () => {
    const { router } = renderApp('/admin/pull-rates')

    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
  })

  it('asks for a game, then for one of its sets', async () => {
    signInAs(admin)
    havePullRates()
    const { router, user } = renderApp('/admin/pull-rates')

    const set = await screen.findByRole('combobox', { name: 'Extension' })
    expect(set).toBeDisabled()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Jeu' }), await screen.findByRole('option', { name: 'Jeu de démo' }))
    await user.click(set)
    await user.click(await screen.findByRole('option', { name: /Aube/ }))

    // In the address: the rates of a set can be linked to.
    expect(router.state.location.search).toBe('?game=demo&set=AUB')
    expect(await boostersField('Rare')).toHaveValue('6')
  })

  it('lists the rarities of the set with their rate, and what it means for one card', async () => {
    signInAs(admin)
    havePullRates()
    renderApp('/admin/pull-rates?game=demo&set=AUB')

    // Four commons in every booster, a rare every six boosters.
    expect(await cardsField('Commune')).toHaveValue('4')
    expect(await boostersField('Commune')).toHaveValue('1')
    expect(await boostersField('Rare')).toHaveValue('6')
    expect(await cardsField('Or')).toHaveValue('')

    // 4 per booster shared between the 40 commons of the set.
    expect(within(screen.getByRole('row', { name: /^Commune 40/ })).getByRole('cell', { name: '1 sur 10' })).toBeInTheDocument()
    // 1 in 6 for a Rare, shared between the 12 Rares.
    expect(within(screen.getByRole('row', { name: /^Rare 12/ })).getByRole('cell', { name: '1 sur 72' })).toBeInTheDocument()
    // What the figures add up to: a way to check them against the size of a booster.
    expect(screen.getByText(/^Total : 4,17 cartes par booster/)).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Source des chiffres' })).toHaveValue('Ouverture de 1 000 boosters')
    expect(screen.getByText('Dernière modification le 8 octobre 2026.')).toBeInTheDocument()
    // Nothing to save yet.
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()
  })

  it('saves the whole list of rates, leaving out the rarities without one', async () => {
    signInAs(admin)
    const saved = havePullRates()
    const { user } = renderApp('/admin/pull-rates?game=demo&set=AUB')

    // Only the boosters: one card is meant.
    await user.type(await boostersField('Or'), '51')
    // What the card page will show, before anything is saved.
    expect(within(screen.getByRole('row', { name: /^Or 3/ })).getByRole('cell', { name: '1 sur 153' })).toBeInTheDocument()
    await user.clear(await cardsField('Rare'))
    await user.clear(await boostersField('Rare'))
    await user.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(saved).toHaveLength(1))
    expect(saved[0]).toEqual({
      rates: { 'rarity-common': { cards: 4, boosters: 1 }, 'rarity-gold': { cards: 1, boosters: 51 } },
      source: 'Ouverture de 1 000 boosters',
    })
    expect(await screen.findByText('Taux enregistrés')).toBeInTheDocument()
    // Back to what is saved, written in full: nothing left to save.
    expect(await cardsField('Or')).toHaveValue('1')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled())
  })

  it('takes several cards of a rarity per booster, and a rate that is not one in a whole number', async () => {
    signInAs(admin)
    const saved = havePullRates()
    const { user } = renderApp('/admin/pull-rates?game=demo&set=AUB')

    // Only the cards: per booster is meant. Two every eleven boosters is "1 in 5.5".
    await user.type(await cardsField('Or'), '3')
    expect(within(screen.getByRole('row', { name: /^Or 3/ })).getByRole('cell', { name: 'dans chaque booster' })).toBeInTheDocument()
    await user.clear(await cardsField('Rare'))
    await user.type(await cardsField('Rare'), '2')
    await user.clear(await boostersField('Rare'))
    await user.type(await boostersField('Rare'), '11')
    expect(within(screen.getByRole('row', { name: /^Rare 12/ })).getByRole('cell', { name: '1 sur 66' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(saved).toHaveLength(1))
    expect((saved[0] as { rates: unknown }).rates).toEqual({
      'rarity-common': { cards: 4, boosters: 1 },
      'rarity-rare': { cards: 2, boosters: 11 },
      'rarity-gold': { cards: 3, boosters: 1 },
    })
  })

  it('refuses what is not a whole number, and says so', async () => {
    signInAs(admin)
    const saved = havePullRates()
    const { user } = renderApp('/admin/pull-rates?game=demo&set=AUB')

    const field = await boostersField('Or')
    await user.type(field, '5,5')

    expect(field).toBeInvalid()
    expect(screen.getByRole('alert')).toHaveTextContent('Les deux cases attendent un nombre entier')
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()
    expect(saved).toHaveLength(0)
  })

  it('says so when the rates could not be saved, and keeps what was typed', async () => {
    signInAs(admin)
    havePullRates()
    server.use(http.put('*/api/admin/sets/:id/pull-rates', () => HttpResponse.json({ error: 'Internal error.' }, { status: 500 })))
    const { user } = renderApp('/admin/pull-rates?game=demo&set=AUB')

    await user.type(await boostersField('Or'), '51')
    await user.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByRole('alert')).toHaveTextContent("Les taux n'ont pas pu être enregistrés.")
    expect(await boostersField('Or')).toHaveValue('51')
  })

  it('says so when the address names a set the game does not have', async () => {
    signInAs(admin)
    renderApp('/admin/pull-rates?game=demo&set=NOPE')

    expect(await screen.findByRole('heading', { name: 'Extension introuvable' })).toBeInTheDocument()
  })
})
