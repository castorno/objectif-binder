import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { haveCollection, ownedCard } from '../../test/collection'
import { emberFox } from '../../test/fixtures'
import { renderApp } from '../../test/render'
import { server } from '../../test/server'
import { signInAs } from '../../test/session'

async function panel() {
  return within(await screen.findByRole('region', { name: 'Ma collection' }))
}

describe('OwnedCardPanel (the "Ma collection" block of a card page)', () => {
  it('invites a visitor to sign in, and brings them back to the card afterwards', async () => {
    const { router, user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    expect(block.queryByRole('button')).not.toBeInTheDocument()
    await user.click(block.getByRole('link', { name: 'Connectez-vous' }))

    expect(router.state.location.pathname).toBe('/login')
    expect(router.state.location.state).toEqual({ from: `/cards/${emberFox.id}` })
  })

  it('adds a card that is not owned yet, in French by default', async () => {
    signInAs()
    const { changes } = haveCollection()
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    expect(await block.findByText('Vous ne possédez pas encore cette carte.')).toBeInTheDocument()
    expect(block.getByRole('combobox', { name: 'Langue' })).toHaveValue('fr')
    await user.click(block.getByRole('button', { name: 'Ajouter à ma collection' }))

    const quantity = await block.findByRole('group', { name: 'Quantité (Français)' })
    expect(within(quantity).getByText('1')).toBeInTheDocument()
    expect(changes).toEqual([
      { method: 'PUT', cardId: emberFox.id, language: 'fr', body: { quantity: 1, condition: null } },
    ])
    expect(block.getByRole('status')).toHaveTextContent('Français : 1 exemplaire dans votre collection.')
    expect(block.queryByText('Vous ne possédez pas encore cette carte.')).not.toBeInTheDocument()
  })

  it('celebrates the first card of an identity, and says how far it takes the user', async () => {
    signInAs()
    haveCollection(
      {},
      {
        [emberFox.id]: {
          identities: [
            { id: 'identity-1', name: 'Renard', sortOrder: 1 },
            { id: 'identity-2', name: 'Chouette', sortOrder: 2 },
          ],
          label: 'Créatures',
          started: 1234,
          total: 2000,
        },
      },
    )
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    await user.click(await block.findByRole('button', { name: 'Ajouter à ma collection' }))

    expect(await screen.findByText('Première carte Renard et Chouette dans votre collection')).toBeInTheDocument()
    expect(screen.getByText('Créatures : 1 234 sur 2 000')).toBeInTheDocument()

    // A second copy is not a first: no second message.
    await user.click(await block.findByRole('button', { name: 'Ajouter un exemplaire (Français)' }))
    await block.findByText('2')
    expect(screen.getAllByText(/Première carte/)).toHaveLength(1)

    await user.click(screen.getByRole('button', { name: 'Fermer le message' }))
    expect(screen.queryByText(/Première carte/)).not.toBeInTheDocument()
  })

  it('says nothing special for a card that starts nothing', async () => {
    signInAs()
    haveCollection()
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    await user.click(await block.findByRole('button', { name: 'Ajouter à ma collection' }))
    await block.findByRole('button', { name: 'Ajouter un exemplaire (Français)' })

    expect(screen.queryByText(/Première carte/)).not.toBeInTheDocument()
  })

  it('changes the quantity while keeping the condition', async () => {
    signInAs()
    const { changes } = haveCollection({ [emberFox.id]: [ownedCard('fr', 2, 'near_mint')] })
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    await user.click(await block.findByRole('button', { name: 'Ajouter un exemplaire (Français)' }))

    const quantity = block.getByRole('group', { name: 'Quantité (Français)' })
    expect(await within(quantity).findByText('3')).toBeInTheDocument()
    // The request carries the whole state: the API clears a condition left out.
    expect(changes.at(-1)?.body).toEqual({ quantity: 3, condition: 'near_mint' })

    await user.click(block.getByRole('button', { name: 'Retirer un exemplaire (Français)' }))

    expect(await within(quantity).findByText('2')).toBeInTheDocument()
    expect(changes.at(-1)?.body).toEqual({ quantity: 2, condition: 'near_mint' })
  })

  it('does not let the minus button go below one copy', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 1)] })
    renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    expect(await block.findByRole('button', { name: 'Retirer un exemplaire (Français)' })).toBeDisabled()
    expect(block.getByRole('button', { name: 'Ajouter un exemplaire (Français)' })).toBeEnabled()
  })

  it('changes the condition while keeping the quantity', async () => {
    signInAs()
    const { changes } = haveCollection({ [emberFox.id]: [ownedCard('fr', 2)] })
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    const condition = await block.findByRole('combobox', { name: 'État (Français)' })
    expect(condition).toHaveValue('')
    await user.selectOptions(condition, 'Played')

    await waitFor(() => expect(condition).toHaveValue('played'))
    expect(changes).toEqual([
      { method: 'PUT', cardId: emberFox.id, language: 'fr', body: { quantity: 2, condition: 'played' } },
    ])
  })

  it('offers only the languages not owned yet, and adds one next to the others', async () => {
    signInAs()
    const { changes } = haveCollection({ [emberFox.id]: [ownedCard('fr', 2)] })
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    const language = await block.findByRole('combobox', { name: 'Langue' })
    expect(within(language).queryByRole('option', { name: 'Français' })).not.toBeInTheDocument()
    await user.selectOptions(language, 'Japonais')
    await user.click(block.getByRole('button', { name: 'Ajouter cette langue' }))

    expect(await block.findByRole('group', { name: 'Quantité (Japonais)' })).toBeInTheDocument()
    expect(block.getByRole('group', { name: 'Quantité (Français)' })).toBeInTheDocument()
    expect(changes.at(-1)).toMatchObject({ method: 'PUT', language: 'ja' })
  })

  it('removes a language, and moves the focus to the form that can add it back', async () => {
    signInAs()
    const { changes } = haveCollection({ [emberFox.id]: [ownedCard('fr', 2), ownedCard('ja', 1)] })
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    await user.click(await block.findByRole('button', { name: 'Retirer Français de ma collection' }))

    await waitFor(() => expect(block.queryByRole('group', { name: 'Quantité (Français)' })).not.toBeInTheDocument())
    expect(block.getByRole('group', { name: 'Quantité (Japonais)' })).toBeInTheDocument()
    expect(changes).toEqual([{ method: 'DELETE', cardId: emberFox.id, language: 'fr' }])
    expect(block.getByRole('status')).toHaveTextContent('Français retiré de votre collection.')
    expect(block.getByRole('combobox', { name: 'Langue' })).toHaveFocus()
  })

  it('says so and keeps the quantity when a change cannot be saved', async () => {
    signInAs()
    haveCollection({ [emberFox.id]: [ownedCard('fr', 2)] })
    server.use(
      http.put('*/api/collection/cards/:id/:language', () =>
        HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 }),
      ),
    )
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    await user.click(await block.findByRole('button', { name: 'Ajouter un exemplaire (Français)' }))

    expect(await block.findByRole('alert')).toHaveTextContent('La modification n\'a pas pu être enregistrée')
    expect(within(block.getByRole('group', { name: 'Quantité (Français)' })).getByText('2')).toBeInTheDocument()
  })

  it('reports a collection that cannot be loaded and recovers when retrying', async () => {
    signInAs()
    let apiIsDown = true
    server.use(
      http.get('*/api/collection/cards/:id', () =>
        apiIsDown
          ? HttpResponse.json({ error: 'Internal Server Error' }, { status: 500 })
          : HttpResponse.json({ data: [ownedCard('fr', 2)] }),
      ),
    )
    const { user } = renderApp(`/cards/${emberFox.id}`)
    const block = await panel()

    const alert = await block.findByRole('alert')
    expect(alert).toHaveTextContent('Impossible de charger votre collection pour cette carte.')
    // The card itself is still shown: only this block failed.
    expect(screen.getByRole('heading', { level: 1, name: 'Renard de braise' })).toBeInTheDocument()

    apiIsDown = false
    await user.click(within(alert).getByRole('button', { name: 'Réessayer' }))

    expect(await block.findByRole('group', { name: 'Quantité (Français)' })).toBeInTheDocument()
  })
})
