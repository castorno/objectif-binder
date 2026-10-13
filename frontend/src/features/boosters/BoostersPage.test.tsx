import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { BoosterList, BoosterOdds } from '../../api/types'
import { demoGame, demoUser } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

const DAWN: BoosterOdds = {
  set: { id: 'set-1', name: 'Aube', code: 'AUB', releaseDate: '2026-01-15', imageUrl: '/api/set-images/aube.png', gameSlug: 'demo', identityLabel: 'Bestiaire' },
  boosterPriceCents: null,
  expectedNewIdentities: 4.4918,
  expectedMissingCards: 4.9993,
  cardsPerBooster: 4.9993,
  identityCount: 101,
  missingIdentityCount: 92,
  cardCount: 191,
  missingCardCount: 191,
  incomplete: false,
  likeliest: [
    { id: 'identity-fox', name: 'Renard', chance: 1 },
    { id: 'identity-owl', name: 'Chouette', chance: 0.1544 },
    { id: 'identity-stag', name: 'Cerf', chance: 0.0032 },
  ],
}

const DUSK: BoosterOdds = {
  ...DAWN,
  set: { id: 'set-2', name: 'Crépuscule', code: 'CRE', releaseDate: null, imageUrl: null, gameSlug: 'demo', identityLabel: null },
  expectedNewIdentities: 1,
  expectedMissingCards: 1.2,
  identityCount: 40,
  missingIdentityCount: 1,
  likeliest: [],
}

function haveBoosters(list: Partial<BoosterList> = {}) {
  const requests: Request[] = []
  server.use(
    http.get('*/api/collection/boosters', ({ request }) => {
      requests.push(request)

      return HttpResponse.json({ data: [DAWN, DUSK], setsWithoutRates: 183, ...list })
    }),
  )

  return requests
}

function booster(name: string) {
  return within(screen.getByRole('article', { name }))
}

/** Figures are formatted with narrow no-break spaces: compare on what is read. */
function text(element: HTMLElement): string {
  return (element.textContent ?? '').replace(/\s+/g, ' ')
}

describe('which booster to open', () => {
  it('is reached from the header once signed in', async () => {
    signInAs()
    haveBoosters()
    const { user } = renderApp('/')

    await user.click(await screen.findByRole('link', { name: 'Boosters' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Quel booster ouvrir ?' })).toBeInTheDocument()
    expect(document.title).toBe('Quel booster ouvrir ? — Objectif Binder')
  })

  it('sends a visitor to the sign-in screen, and shows them no link to it', async () => {
    const requests = haveBoosters()
    const { router } = renderApp('/boosters')

    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
    expect(screen.queryByRole('link', { name: 'Boosters' })).not.toBeInTheDocument()
    expect(requests).toHaveLength(0)
  })

  it('says what a booster of each set brings on average, in the order the API ranks them', async () => {
    signInAs()
    haveBoosters()
    renderApp('/boosters')

    await screen.findByRole('article', { name: 'Aube' })
    expect(screen.getAllByRole('article').map((article) => within(article).getByRole('heading').textContent)).toEqual([
      'Aube',
      'Crépuscule',
    ])

    const dawn = booster('Aube')
    // The figure that ranks the boosters.
    expect(text(dawn.getByText('4,5').closest('p')!)).toBe('4,5 nouvelles entrées par booster')
    expect(text(dawn.getByText(/que vous n'avez pas/))).toBe("5 cartes que vous n'avez pas par booster")
    // What is already started, under the name the game gives its identities.
    expect(text(dawn.getByText('Bestiaire').closest('p')!)).toBe('Bestiaire9 / 101 avec au moins une carte')
    expect(dawn.getByText('janv. 2026')).toBeInTheDocument()

    // Singular, and a plain word when the game gives its identities no name.
    const dusk = booster('Crépuscule')
    expect(text(dusk.getByText('1', { selector: 'span' }).closest('p')!)).toBe('1 nouvelle entrée par booster')
    expect(text(dusk.getByText(/que vous n'avez pas/))).toBe("1,2 carte que vous n'avez pas par booster")
    expect(text(dusk.getByText('Entrées').closest('p')!)).toBe('Entrées39 / 40 avec au moins une carte')
  })

  it('shows how much of the cards of a set is owned, next to its entries', async () => {
    signInAs()
    haveBoosters({ data: [{ ...DAWN, missingCardCount: 150 }] })
    renderApp('/boosters')

    await screen.findByRole('article', { name: 'Aube' })
    expect(text(booster('Aube').getByText('Cartes').closest('p')!)).toBe('Cartes41 / 191 possédées')
  })

  it('narrows the ranking to a game, kept in the address, and ranks again from the first', async () => {
    signInAs()
    const otherGame = { ...demoGame, id: 'game-2', name: 'Autre jeu', slug: 'other' }
    server.use(http.get('*/api/games', () => HttpResponse.json([demoGame, otherGame])))
    haveBoosters({
      data: [DAWN, { ...DUSK, set: { ...DUSK.set, gameSlug: 'other' } }],
    })
    const { router, user } = renderApp('/boosters')
    await screen.findByRole('article', { name: 'Aube' })
    expect(screen.getByText(/183 extensions n'ont pas encore/)).toBeInTheDocument()

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Jeu' }), 'Autre jeu')

    expect(screen.getAllByRole('article').map((article) => within(article).getByRole('heading').textContent)).toEqual(['Crépuscule'])
    // The only one left is the first.
    expect(booster('Crépuscule').getByText('1', { selector: 'span[aria-hidden]' })).toBeInTheDocument()
    expect(router.state.location.search).toBe('?game=other')
    // The sets without rates are counted over every game.
    expect(screen.queryByText(/n'ont pas encore/)).not.toBeInTheDocument()
  })

  it('asks the API for the boosters of a language, kept in the address next to the game', async () => {
    signInAs()
    const requests = haveBoosters()
    const { router, user } = renderApp('/boosters?game=demo')
    await screen.findByRole('article', { name: 'Aube' })
    expect(new URL(requests[0].url).searchParams.has('language')).toBe(false)

    await user.selectOptions(screen.getByRole('combobox', { name: 'Langue des boosters' }), 'Français')

    await waitFor(() => expect(new URL(requests.at(-1)!.url).searchParams.get('language')).toBe('fr'))
    expect(router.state.location.search).toBe('?game=demo&language=fr')
    expect(await screen.findByRole('combobox', { name: 'Langue des boosters' })).toHaveValue('fr')
  })

  it('shows the game without letting it be changed when every booster is of the same one', async () => {
    signInAs()
    haveBoosters()
    renderApp('/boosters')

    await screen.findByRole('article', { name: 'Aube' })
    const field = screen.getByRole('combobox', { name: 'Jeu' })
    expect(field).toBeDisabled()
    await waitFor(() => expect(field).toHaveDisplayValue('Jeu de démo'))
  })

  it('names the new entries a booster is most likely to give, each leading to its cards', async () => {
    signInAs()
    haveBoosters()
    renderApp('/boosters')
    await screen.findByRole('article', { name: 'Aube' })

    const likeliest = booster('Aube').getByText(/Les plus probables/).closest('p')!
    expect(text(likeliest)).toBe('Les plus probables : Renard 100 % · Chouette 15 % · Cerf 0,32 %')
    expect(within(likeliest).getByRole('link', { name: 'Chouette' })).toHaveAttribute(
      'href',
      '/?game=demo&identity=identity-owl',
    )
    // Nothing to name: no such line.
    expect(booster('Crépuscule').queryByText(/Les plus probables/)).not.toBeInTheDocument()
  })

  it('leads to the cards of the set the user does not own yet', async () => {
    signInAs()
    haveBoosters()
    renderApp('/boosters')
    await screen.findByRole('article', { name: 'Aube' })

    expect(booster('Aube').getByRole('link', { name: "Voir les cartes qu'il vous manque" })).toHaveAttribute(
      'href',
      '/?game=demo&set=AUB&ownership=missing',
    )
  })

  it('shows what a booster costs and what a euro brings, and ranks by euro on request', async () => {
    signInAs()
    // Dawn brings more per booster, Dusk more per euro: 4.49 / 8 € against 1 / 1 €.
    haveBoosters({
      data: [
        { ...DAWN, boosterPriceCents: 800 },
        { ...DUSK, boosterPriceCents: 100 },
        { ...DUSK, set: { ...DUSK.set, id: 'set-3', name: 'Zénith', code: 'ZEN' } },
      ],
    })
    const { router, user } = renderApp('/boosters')
    await screen.findByRole('article', { name: 'Aube' })
    const order = () => screen.getAllByRole('article').map((article) => within(article).getByRole('heading').textContent)

    expect(text(booster('Aube').getByText(/le booster/))).toBe('janv. 2026 · 8,00 € le booster')
    expect(text(booster('Aube').getByText('4,5').closest('p')!)).toBe('4,5 nouvelles entrées par booster · 0,56 par euro')
    // No price, no figure per euro.
    expect(booster('Zénith').queryByText(/par euro/)).not.toBeInTheDocument()
    expect(order()).toEqual(['Aube', 'Crépuscule', 'Zénith'])

    await user.click(screen.getByRole('button', { name: 'Par euro' }))

    // A booster without price cannot be ranked per euro: it goes last.
    expect(order()).toEqual(['Crépuscule', 'Aube', 'Zénith'])
    expect(screen.getByRole('button', { name: 'Par euro' })).toHaveAttribute('aria-pressed', 'true')
    // The figure that ranks is the one put forward; without price, the one per booster stays.
    expect(text(booster('Aube').getByText('0,56').closest('p')!)).toBe('0,56 nouvelle entrée par euro · 4,5 par booster')
    expect(text(booster('Zénith').getByText('1', { selector: 'span.text-3xl' }).closest('p')!)).toBe('1 nouvelle entrée par booster')
    // In the address, so that a link keeps the order.
    expect(router.state.location.search).toBe('?sort=euro')

    await user.click(screen.getByRole('button', { name: 'Par booster' }))
    expect(order()).toEqual(['Aube', 'Crépuscule', 'Zénith'])
  })

  it('offers no ranking per euro while no booster has a price', async () => {
    signInAs()
    haveBoosters()
    renderApp('/boosters?sort=euro')
    await screen.findByRole('article', { name: 'Aube' })

    expect(screen.queryByRole('button', { name: 'Par euro' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('article').map((article) => within(article).getByRole('heading').textContent)).toEqual([
      'Aube',
      'Crépuscule',
    ])
  })

  it('shows the picture of a set that has one, next to its name', async () => {
    signInAs()
    haveBoosters()
    renderApp('/boosters')
    const dawn = await screen.findByRole('article', { name: 'Aube' })

    // Decorative: the name is written next to it, so it has no text of its own.
    expect(dawn.querySelector('img')).toHaveAttribute('src', '/api/set-images/aube.png')
    expect(dawn.querySelector('img')).toHaveAttribute('alt', '')
    expect(screen.getByRole('article', { name: 'Crépuscule' }).querySelector('img')).toBeNull()
  })

  it('warns when the pull rates of a booster are not all entered', async () => {
    signInAs()
    haveBoosters({ data: [{ ...DAWN, incomplete: true }, DUSK] })
    renderApp('/boosters')
    await screen.findByRole('article', { name: 'Aube' })

    expect(booster('Aube').getByText(/Taux de drop incomplets/)).toBeInTheDocument()
    expect(booster('Crépuscule').queryByText(/Taux de drop incomplets/)).not.toBeInTheDocument()
  })

  it('says so when the user already has every entry of a set', async () => {
    signInAs()
    haveBoosters({ data: [{ ...DAWN, expectedNewIdentities: 0, missingIdentityCount: 0, likeliest: [] }] })
    renderApp('/boosters')
    await screen.findByRole('article', { name: 'Aube' })

    expect(booster('Aube').getByText('Vous avez déjà au moins une carte de chacune de ses 101 entrées.')).toBeInTheDocument()
    expect(booster('Aube').queryByText(/nouvelles? entrées? .*par booster/)).not.toBeInTheDocument()
  })

  it('says how many sets are left out because their pull rates are not entered', async () => {
    signInAs()
    haveBoosters()
    renderApp('/boosters')
    await screen.findByRole('article', { name: 'Aube' })

    expect(screen.getByText(/183 extensions n'ont pas encore leurs taux de drop\./)).toBeInTheDocument()
    // Entering them is not for everyone.
    expect(screen.queryByRole('link', { name: 'Renseigner des taux' })).not.toBeInTheDocument()
  })

  it('shows an administrator where to enter the missing rates', async () => {
    signInAs({ ...demoUser, isAdmin: true })
    haveBoosters()
    renderApp('/boosters')

    expect(await screen.findByRole('link', { name: 'Renseigner des taux' })).toHaveAttribute('href', '/admin/pull-rates')
  })

  it('says so when no set has its pull rates yet', async () => {
    signInAs()
    haveBoosters({ data: [], setsWithoutRates: 1 })
    renderApp('/boosters')

    expect(await screen.findByRole('heading', { name: "Aucune extension n'a encore ses taux de drop" })).toBeInTheDocument()
    expect(screen.getByText(/1 extension n'a pas encore ses taux de drop\./)).toBeInTheDocument()
  })

  it('reports a list that cannot be loaded and recovers when retrying', async () => {
    signInAs()
    let apiIsDown = true
    server.use(
      http.get('*/api/collection/boosters', () =>
        apiIsDown
          ? HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 })
          : HttpResponse.json({ data: [DAWN], setsWithoutRates: 0 }),
      ),
    )
    const { user } = renderApp('/boosters')

    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de charger les boosters')
    apiIsDown = false
    await user.click(screen.getByRole('button', { name: 'Réessayer' }))

    expect(await screen.findByRole('article', { name: 'Aube' })).toBeInTheDocument()
  })
})
