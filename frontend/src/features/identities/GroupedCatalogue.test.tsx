import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { haveCollection } from '../../test/collection'
import { cardPage, demoGame, demoIdentities, emberFox, foxIdentity, identityPage, mistOwl, owlIdentity } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

function entryNames() {
  return screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)
}

function entry(name: string) {
  return within(screen.getByRole('heading', { level: 3, name }).closest('li')!)
}

function viewSwitch() {
  return within(screen.getByRole('navigation', { name: 'Affichage du catalogue' }))
}

/** Records the URLs a route was asked with, answering `body`. */
function watch(path: string, body: unknown) {
  const requests: URL[] = []
  server.use(
    http.get(path, ({ request }) => {
      requests.push(new URL(request.url))

      return HttpResponse.json(body as Record<string, unknown>)
    }),
  )

  return requests
}

describe('grouped catalogue (one entry per identity)', () => {
  it('is one click away from the list of cards, under a generic name without a game', async () => {
    const { router, user } = renderApp()

    await screen.findByRole('heading', { level: 3, name: emberFox.name })
    expect(viewSwitch().getByRole('link', { name: 'Cartes' })).toHaveAttribute('aria-current', 'page')
    await user.click(viewSwitch().getByRole('link', { name: 'Regroupées' }))

    await waitFor(() => expect(entryNames()).toEqual(['Renard', 'Chouette', 'Autres cartes']))
    expect(router.state.location.search).toBe('?view=identities')
    expect(viewSwitch().getByRole('link', { name: 'Regroupées' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('status')).toHaveTextContent('2 groupes')
  })

  it('takes the name of what the selected game groups its cards by', async () => {
    renderApp('/?game=demo')

    // The demo game calls its identities "Créatures".
    expect(await viewSwitch().findByRole('link', { name: 'Créatures' })).toHaveAttribute(
      'href',
      '/?game=demo&view=identities',
    )
  })

  it('shows how many cards each entry groups, and the cards without identity last', async () => {
    renderApp('/?view=identities')

    await waitFor(() => expect(entryNames()).toEqual(['Renard', 'Chouette', 'Autres cartes']))

    expect(entry('Renard').getByText('2 cartes')).toBeInTheDocument()
    expect(entry('Chouette').getByText('1 carte')).toBeInTheDocument()
    expect(entry('Autres cartes').getByText('3 cartes')).toBeInTheDocument()
  })

  it('is searched by name and game only', async () => {
    const requests = watch('*/api/identities', identityPage([foxIdentity]))
    renderApp('/?view=identities&q=renard&game=demo')

    await waitFor(() => expect(entryNames()).toEqual(['Renard']))

    expect(Object.fromEntries(requests[0].searchParams)).toEqual({ q: 'renard', game: 'demo', page: '1', limit: '20' })
    expect(screen.getByRole('searchbox', { name: 'Nom' })).toHaveValue('renard')
    expect(screen.queryByRole('combobox', { name: 'Extension' })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Rareté' })).not.toBeInTheDocument()
    // The cards without identity do not match a name.
    expect(screen.queryByRole('heading', { level: 3, name: 'Autres cartes' })).not.toBeInTheDocument()
  })

  it('narrows the entries and the progress to one group, under the name the game gives its groups', async () => {
    signInAs()
    server.use(
      http.get('*/api/games', () => HttpResponse.json([{ ...demoGame, identityGroupLabel: 'Génération' }])),
      http.get('*/api/games/:slug/identity-groups', () =>
        HttpResponse.json([
          { name: 'Génération 1', identityCount: 151 },
          { name: 'Génération 2', identityCount: 100 },
        ]),
      ),
    )
    const lists = watch('*/api/identities', identityPage(demoIdentities))
    const progress = watch('*/api/collection/identities', {
      totalIdentities: 100,
      startedIdentities: 4,
      ownedByIdentity: {},
      ownedWithoutIdentity: 0,
    })
    const { router, user } = renderApp('/?view=identities&game=demo')

    const filter = await screen.findByRole('combobox', { name: 'Génération' })
    await within(filter).findByRole('option', { name: 'Génération 2 (100)' })
    await user.selectOptions(filter, 'Génération 2 (100)')

    // The list and the user's progress are asked for the same group.
    await waitFor(() => expect(lists.at(-1)?.searchParams.get('group')).toBe('Génération 2'))
    await waitFor(() => expect(progress.at(-1)?.searchParams.get('group')).toBe('Génération 2'))
    expect(new URLSearchParams(router.state.location.search).get('group')).toBe('Génération 2')

    // Another game has other groups: the choice does not follow.
    await user.selectOptions(screen.getByRole('combobox', { name: 'Jeu' }), '')
    await waitFor(() => expect(new URLSearchParams(router.state.location.search).has('group')).toBe(false))
  })

  it('offers no group filter for a game that has none, nor in the list of cards', async () => {
    server.use(http.get('*/api/games', () => HttpResponse.json([{ ...demoGame, identityGroupLabel: 'Génération' }])))
    renderApp('/?view=identities&game=demo')
    await screen.findByRole('heading', { level: 3, name: foxIdentity.name })
    expect(screen.queryByRole('combobox', { name: 'Génération' })).not.toBeInTheDocument()

    server.use(http.get('*/api/games/:slug/identity-groups', () => HttpResponse.json([{ name: 'Génération 1', identityCount: 151 }])))
    renderApp('/?game=demo')
    await screen.findAllByRole('heading', { level: 3, name: emberFox.name })
    expect(screen.queryByRole('combobox', { name: 'Génération' })).not.toBeInTheDocument()
  })

  it('opens the cards of an entry, as a filter of the catalogue', async () => {
    const cardRequests = watch('*/api/cards', cardPage([emberFox]))
    const { router, user } = renderApp('/?view=identities&game=demo')

    await user.click(await screen.findByRole('heading', { level: 3, name: 'Renard' }))

    await waitFor(() => expect(entryNames()).toEqual([emberFox.name]))
    expect(router.state.location.search).toBe(`?game=demo&identity=${foxIdentity.id}`)
    expect(cardRequests.at(-1)?.searchParams.get('identity')).toBe(foxIdentity.id)
    // Says which entry is open, by its name.
    expect(await screen.findByRole('button', { name: 'Retirer le filtre Renard' })).toBeInTheDocument()
    // And the way back is the view switch.
    expect(viewSwitch().getByRole('link', { name: 'Créatures' })).toHaveAttribute('href', '/?game=demo&view=identities')
  })

  it('goes back to every card when the entry is removed from the filters', async () => {
    const { router, user } = renderApp(`/?game=demo&identity=${foxIdentity.id}`)

    await user.click(await screen.findByRole('button', { name: 'Retirer le filtre Renard' }))

    await waitFor(() => expect(router.state.location.search).toBe('?game=demo'))
    expect(screen.queryByRole('button', { name: /Retirer le filtre/ })).not.toBeInTheDocument()
  })

  it('opens the cards without identity from the last entry', async () => {
    const cardRequests = watch('*/api/cards', cardPage([mistOwl]))
    const { router, user } = renderApp('/?view=identities&game=demo')

    await user.click(await screen.findByRole('heading', { level: 3, name: 'Autres cartes' }))

    await waitFor(() => expect(entryNames()).toEqual([mistOwl.name]))
    expect(router.state.location.search).toBe('?game=demo&identity=none')
    expect(cardRequests.at(-1)?.searchParams.get('identity')).toBe('none')
    expect(screen.getByRole('button', { name: 'Retirer le filtre Autres cartes' })).toBeInTheDocument()
  })

  it('shows a signed-in user how much of each entry they own', async () => {
    signInAs()
    haveCollection()
    const ownedRequests = watch('*/api/collection/identities', {
      totalIdentities: 2,
      startedIdentities: 1,
      ownedByIdentity: { [foxIdentity.id]: 1 },
      ownedWithoutIdentity: 2,
    })
    renderApp('/?view=identities&game=demo')

    await waitFor(() => expect(entry('Renard').getByText(/1 \/ 2/)).toBeInTheDocument())

    // Nothing owned of an entry is still a progress, at zero.
    expect(entry('Chouette').getByText(/0 \/ 1/)).toBeInTheDocument()
    expect(entry('Autres cartes').getByText(/2 \/ 3/)).toBeInTheDocument()
    expect(Object.fromEntries(ownedRequests[0].searchParams)).toEqual({ game: 'demo', page: '1', limit: '20' })
  })

  it('shows how many entries are started: those with at least one card owned', async () => {
    signInAs()
    haveCollection()
    watch('*/api/collection/identities', {
      totalIdentities: 6,
      startedIdentities: 4,
      ownedByIdentity: {},
      ownedWithoutIdentity: 0,
    })
    renderApp('/?view=identities&game=demo')

    const progress = await screen.findByRole('region', { name: 'Progression' })
    expect(progress).toHaveTextContent('66 %')
    const bar = within(progress).getByRole('progressbar')
    expect(bar).toHaveAttribute('aria-valuenow', '4')
    expect(bar).toHaveAttribute('aria-valuemax', '6')
    expect(bar).toHaveAttribute('aria-valuetext', '4 avec au moins une carte · 2 sans aucune carte')
  })

  it('shows a visitor no progress, and does not ask the API for one', async () => {
    const ownedRequests = watch('*/api/collection/identities', {
      totalIdentities: 2,
      startedIdentities: 0,
      ownedByIdentity: {},
      ownedWithoutIdentity: 0,
    })
    renderApp('/?view=identities')

    await waitFor(() => expect(entryNames()).toEqual(['Renard', 'Chouette', 'Autres cartes']))
    await screen.findByRole('link', { name: 'Se connecter' })

    expect(screen.queryByText(/\d \/ \d/)).not.toBeInTheDocument()
    expect(ownedRequests).toHaveLength(0)
  })

  it('does not load the cards while it is on screen', async () => {
    const cardRequests = watch('*/api/cards', cardPage([emberFox]))
    renderApp('/?view=identities')

    await waitFor(() => expect(entryNames()).toEqual(['Renard', 'Chouette', 'Autres cartes']))

    expect(cardRequests).toHaveLength(0)
    expect(screen.queryByRole('region', { name: 'Progression' })).not.toBeInTheDocument()
  })

  it('says so when a game does not group its cards', async () => {
    server.use(http.get('*/api/identities', () => HttpResponse.json(identityPage([], { cardsWithoutIdentity: 0 }))))
    renderApp('/?view=identities&game=demo')

    expect(await screen.findByRole('heading', { name: 'Aucun regroupement de cartes ici' })).toBeInTheDocument()
  })

  it('keeps the grouped view when the filters are reset', async () => {
    server.use(
      http.get('*/api/identities', ({ request }) =>
        HttpResponse.json(new URL(request.url).searchParams.get('q') ? identityPage([]) : identityPage(demoIdentities)),
      ),
    )
    const { router, user } = renderApp('/?view=identities&q=dragon')

    expect(await screen.findByRole('heading', { name: 'Aucun regroupement ne correspond à cette recherche' })).toBeInTheDocument()
    await user.click(within(screen.getByRole('search')).getByRole('button', { name: 'Réinitialiser les filtres' }))

    await waitFor(() => expect(entryNames()).toEqual(['Renard', 'Chouette', 'Autres cartes']))
    expect(router.state.location.search).toBe('?view=identities')
  })

  it('reports a list that cannot be loaded and recovers when retrying', async () => {
    let apiIsDown = true
    server.use(
      http.get('*/api/identities', () =>
        apiIsDown
          ? HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 })
          : HttpResponse.json(identityPage([owlIdentity])),
      ),
    )
    const { user } = renderApp('/?view=identities')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Impossible de charger les regroupements')

    apiIsDown = false
    await user.click(within(alert).getByRole('button', { name: 'Réessayer' }))

    await waitFor(() => expect(entryNames()).toContain('Chouette'))
  })

  it('links a card page to the other cards of its identity', async () => {
    const { router, user } = renderApp(`/cards/${emberFox.id}`)

    await user.click(await screen.findByRole('link', { name: 'Toutes les cartes « Renard »' }))

    await waitFor(() => expect(router.state.location.search).toBe(`?game=demo&identity=${foxIdentity.id}`))
  })

  it('shows no such link on a card without identity', async () => {
    renderApp(`/cards/${mistOwl.id}`)

    await screen.findByRole('heading', { level: 1, name: mistOwl.name })

    expect(screen.queryByRole('link', { name: /Toutes les cartes «/ })).not.toBeInTheDocument()
  })
})
