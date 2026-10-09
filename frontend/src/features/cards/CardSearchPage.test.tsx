import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { cardPage, demoCards, demoRarities, emberFox } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'

/**
 * Answers card searches with `respond` and records the query string of each
 * one, to check what the screen actually asked the API.
 */
function mockCardSearch(respond: (query: URLSearchParams) => Response = () => HttpResponse.json(cardPage(demoCards))) {
  const queries: URLSearchParams[] = []
  server.use(
    http.get('*/api/cards', ({ request }) => {
      const query = new URL(request.url).searchParams
      queries.push(query)

      return respond(query)
    }),
  )

  return queries
}

function results() {
  return within(screen.getByRole('region', { name: 'Résultats' }))
}

describe('CardSearchPage', () => {
  describe('results', () => {
    it('lists the cards and announces how many match', async () => {
      mockCardSearch(() => HttpResponse.json(cardPage(demoCards, { total: 120, totalPages: 6 })))
      renderApp()

      expect(await screen.findByRole('heading', { name: 'Renard de braise' })).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: 'Chouette des brumes' })).toBeInTheDocument()
      expect(screen.getByRole('status')).toHaveTextContent(/^120 cartes$/)
      expect(document.title).toBe('Catalogue — Objectif Binder')
    })

    it('uses the singular for a single card', async () => {
      mockCardSearch(() => HttpResponse.json(cardPage([emberFox])))
      renderApp()

      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent(/^1 carte$/))
    })

    it('asks the API for the first page of 20 cards when nothing is filtered', async () => {
      const queries = mockCardSearch()
      renderApp()

      await screen.findByRole('heading', { name: 'Renard de braise' })

      expect(queries.map((query) => Object.fromEntries(query))).toEqual([{ page: '1', limit: '20' }])
    })
  })

  describe('empty and error states', () => {
    it('says the catalogue is empty when there is no card and no filter', async () => {
      mockCardSearch(() => HttpResponse.json(cardPage([])))
      renderApp()

      expect(await screen.findByRole('heading', { name: 'Le catalogue est vide' })).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: 'Réinitialiser les filtres' })).not.toBeInTheDocument()
    })

    it('offers to reset the filters when a search matches nothing', async () => {
      mockCardSearch((query) => HttpResponse.json(cardPage(query.has('q') ? [] : demoCards)))
      const { router, user } = renderApp('/?q=zzz')

      expect(
        await screen.findByRole('heading', { name: 'Aucune carte ne correspond à cette recherche' }),
      ).toBeInTheDocument()

      await user.click(results().getByRole('button', { name: 'Réinitialiser les filtres' }))

      expect(await screen.findByRole('heading', { name: 'Renard de braise' })).toBeInTheDocument()
      expect(screen.getByRole('searchbox', { name: 'Nom de la carte' })).toHaveValue('')
      expect(router.state.location.search).toBe('')
    })

    it('reports a server error and recovers when retrying', async () => {
      let apiIsDown = true
      mockCardSearch(() =>
        apiIsDown
          ? HttpResponse.json({ error: 'Internal error.' }, { status: 500 })
          : HttpResponse.json(cardPage(demoCards)),
      )
      const { user } = renderApp()

      const alert = await screen.findByRole('alert')
      expect(alert).toHaveTextContent('Impossible de charger les cartes')

      apiIsDown = false
      await user.click(within(alert).getByRole('button', { name: 'Réessayer' }))

      expect(await screen.findByRole('heading', { name: 'Renard de braise' })).toBeInTheDocument()
      expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    })

    it('leads back to the first page from a page number that does not exist', async () => {
      mockCardSearch((query) =>
        HttpResponse.json(
          query.get('page') === '1'
            ? cardPage(demoCards)
            : cardPage([], { total: demoCards.length, page: 99, totalPages: 1 }),
        ),
      )
      const { router, user } = renderApp('/?game=demo&page=99')

      expect(
        await screen.findByRole('heading', { name: "Cette page de résultats n'existe pas" }),
      ).toBeInTheDocument()

      await user.click(screen.getByRole('link', { name: 'Revenir à la première page' }))

      expect(await screen.findByRole('heading', { name: 'Renard de braise' })).toBeInTheDocument()
      expect(router.state.location.search).toBe('?game=demo')
    })
  })

  describe('filters', () => {
    it('searches by name once the user stops typing, not on every keystroke', async () => {
      const queries = mockCardSearch()
      const { router, user } = renderApp()
      await screen.findByRole('heading', { name: 'Renard de braise' })

      await user.type(screen.getByRole('searchbox', { name: 'Nom de la carte' }), 'renard')

      await waitFor(() => expect(queries.at(-1)?.get('q')).toBe('renard'))
      expect(queries.map((query) => query.get('q'))).toEqual([null, 'renard'])
      expect(router.state.location.search).toBe('?q=renard')
    })

    it('searches at once when the form is submitted', async () => {
      const queries = mockCardSearch()
      const { user } = renderApp()
      await screen.findByRole('heading', { name: 'Renard de braise' })

      await user.type(screen.getByRole('searchbox', { name: 'Nom de la carte' }), 'renard{Enter}')

      // Well under the 300 ms debounce.
      await waitFor(() => expect(queries.at(-1)?.get('q')).toBe('renard'), { timeout: 150 })
    })

    it('unlocks the set and rarity filters once a game is chosen', async () => {
      const queries = mockCardSearch()
      const { user } = renderApp()

      const set = screen.getByRole('combobox', { name: 'Extension' })
      const rarity = screen.getByRole('combobox', { name: 'Rareté' })
      expect(set).toBeDisabled()
      expect(rarity).toBeDisabled()
      expect(set).toHaveAccessibleDescription('Choisissez un jeu pour filtrer par extension et par rareté.')

      await screen.findByRole('option', { name: 'Jeu de démo' })
      await user.selectOptions(screen.getByRole('combobox', { name: 'Jeu' }), 'Jeu de démo')

      expect(set).toBeEnabled()
      expect(rarity).toBeEnabled()
      await user.click(set)
      expect(await screen.findByRole('option', { name: /Aube/ })).toBeInTheDocument()
      expect(await within(rarity).findByRole('option', { name: 'Rare' })).toBeInTheDocument()
      await waitFor(() => expect(queries.at(-1)?.get('game')).toBe('demo'))
    })

    it('filters by set, sending its code to the API', async () => {
      const queries = mockCardSearch()
      const { router, user } = renderApp('/?game=demo')

      // Typed without its accent, and only in part.
      await user.type(screen.getByRole('combobox', { name: 'Extension' }), 'crepu')
      await user.click(await screen.findByRole('option', { name: /Crépuscule/ }))

      await waitFor(() => expect(queries.at(-1)?.get('set')).toBe('CRE'))
      expect(router.state.location.search).toBe('?game=demo&set=CRE')
      expect(screen.getByRole('combobox', { name: 'Extension' })).toHaveValue('Crépuscule')
    })

    it('shows when each set came out, next to its name', async () => {
      mockCardSearch()
      const { user } = renderApp('/?game=demo')

      await user.click(screen.getByRole('combobox', { name: 'Extension' }))

      expect(await screen.findByRole('option', { name: 'Aube janv. 2026 · AUB' })).toBeInTheDocument()
      // A set without release date only shows its code.
      expect(screen.getByRole('option', { name: 'Crépuscule CRE' })).toBeInTheDocument()
    })

    it('only offers the rarities of the chosen set, and drops a rarity that set does not have', async () => {
      const queries = mockCardSearch()
      server.use(
        http.get('*/api/games/:slug/rarities', ({ request }) =>
          // The second set only has commons.
          HttpResponse.json(new URL(request.url).searchParams.get('set') === 'CRE' ? [demoRarities[0]] : demoRarities),
        ),
      )
      const { router, user } = renderApp('/?game=demo&rarity=Rare')

      const rarity = screen.getByRole('combobox', { name: 'Rareté' })
      await within(rarity).findByRole('option', { name: 'Rare' })

      await user.click(screen.getByRole('combobox', { name: 'Extension' }))
      await user.click(await screen.findByRole('option', { name: /Crépuscule/ }))

      await waitFor(() => expect(within(rarity).queryByRole('option', { name: 'Rare' })).not.toBeInTheDocument())
      expect(within(rarity).getByRole('option', { name: 'Commune' })).toBeInTheDocument()
      // "Rare" would find nothing in this set: the filter lets go of it.
      await waitFor(() => expect(router.state.location.search).toBe('?game=demo&set=CRE'))
      expect(queries.at(-1)?.get('rarity')).toBeNull()
    })

    it('keeps the chosen rarity when the set picked has it too', async () => {
      mockCardSearch()
      const { router, user } = renderApp('/?game=demo&rarity=Rare')

      await user.click(screen.getByRole('combobox', { name: 'Extension' }))
      await user.click(await screen.findByRole('option', { name: /Aube/ }))

      await waitFor(() => expect(router.state.location.search).toBe('?game=demo&set=AUB&rarity=Rare'))
      expect(screen.getByRole('combobox', { name: 'Rareté' })).toHaveValue('Rare')
    })

    it('marks the sets without any picture, in a game that has pictures', async () => {
      mockCardSearch()
      server.use(
        http.get('*/api/games/:slug/sets', () =>
          HttpResponse.json([
            { id: 'set-1', name: 'Aube', code: 'AUB', releaseDate: null, hasPictures: true },
            { id: 'set-2', name: 'Crépuscule', code: 'CRE', releaseDate: null, hasPictures: false },
          ]),
        ),
      )
      const { user } = renderApp('/?game=demo')

      await user.click(screen.getByRole('combobox', { name: 'Extension' }))

      // Said in words too, for those who do not see the sign.
      expect(await screen.findByRole('option', { name: /^Crépuscule\ssans images CRE$/ })).toBeInTheDocument()
      expect(screen.getByRole('option', { name: 'Aube AUB' })).toBeInTheDocument()
    })

    it('warns when the filter options cannot be loaded, without hiding the cards', async () => {
      server.use(http.get('*/api/games', () => HttpResponse.json({ error: 'Internal error.' }, { status: 500 })))
      renderApp()

      expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de charger certaines options de filtre.')
      expect(await screen.findByRole('heading', { name: 'Renard de braise' })).toBeInTheDocument()
    })
  })

  describe('pagination', () => {
    it('loads the requested page and keeps the filters in the URL', async () => {
      const queries = mockCardSearch((query) =>
        HttpResponse.json(cardPage(demoCards, { total: 40, page: Number(query.get('page')), totalPages: 2 })),
      )
      const { router, user } = renderApp('/?game=demo')

      await user.click(await screen.findByRole('link', { name: 'Page 2' }))

      await waitFor(() => expect(screen.getByRole('link', { name: 'Page 2' })).toHaveAttribute('aria-current', 'page'))
      expect(queries.at(-1)?.get('page')).toBe('2')
      expect(router.state.location.search).toBe('?game=demo&page=2')
    })
  })
})
