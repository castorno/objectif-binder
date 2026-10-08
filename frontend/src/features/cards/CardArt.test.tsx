import { fireEvent, render } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { CardArt } from './CardArt'

const PICTURE = 'https://images.example.org/aub/012/low.webp'

function renderArt(imageUrl?: string | null) {
  return render(<CardArt name="Renard de braise" setCode="AUB" numberInSet="012" imageUrl={imageUrl} />)
}

describe('CardArt', () => {
  it('shows the generated stand-in for a card without picture', () => {
    const { container, getByText } = renderArt(null)

    expect(container.querySelector('img')).toBeNull()
    expect(getByText('AUB')).toBeInTheDocument()
    expect(getByText('012')).toBeInTheDocument()
  })

  it('shows the picture of a card that has one, without asking for it too early', () => {
    const { container, queryByText } = renderArt(PICTURE)

    const picture = container.querySelector('img')
    expect(picture).toHaveAttribute('src', PICTURE)
    // Decorative: the name of the card is in the text next to it.
    expect(picture).toHaveAttribute('alt', '')
    expect(picture).toHaveAttribute('loading', 'lazy')
    expect(picture).toHaveAttribute('referrerpolicy', 'no-referrer')
    expect(queryByText('012')).not.toBeInTheDocument()
  })

  it('falls back to the stand-in when the picture cannot be loaded', () => {
    const { container, getByText } = renderArt(PICTURE)

    fireEvent.error(container.querySelector('img')!)

    expect(container.querySelector('img')).toBeNull()
    expect(getByText('012')).toBeInTheDocument()
  })

  it('gives another picture its chance after one failed', () => {
    const { container, rerender } = renderArt(PICTURE)
    fireEvent.error(container.querySelector('img')!)

    const other = 'https://images.example.org/aub/013/low.webp'
    rerender(<CardArt name="Chouette" setCode="AUB" numberInSet="013" imageUrl={other} />)

    expect(container.querySelector('img')).toHaveAttribute('src', other)
  })
})
