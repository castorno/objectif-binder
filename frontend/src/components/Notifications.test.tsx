import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { NotificationsProvider } from './Notifications'
import { useNotify } from './useNotify'

function Trigger() {
  const notify = useNotify()
  let count = 0

  return (
    <button type="button" onClick={() => notify({ title: `Message ${++count}`, detail: 'Détail' })}>
      Notifier
    </button>
  )
}

afterEach(() => vi.useRealTimers())

describe('notifications', () => {
  it('shows a message in a region screen readers follow, then removes it by itself', () => {
    vi.useFakeTimers()
    const { container } = render(
      <NotificationsProvider>
        <Trigger />
      </NotificationsProvider>,
    )
    // There before any message: a region added with its content is not announced.
    const region = container.querySelector('[aria-live="polite"]')
    expect(region).toBeEmptyDOMElement()

    act(() => screen.getByRole('button', { name: 'Notifier' }).click())
    expect(region).toHaveTextContent('Message 1')
    expect(region).toHaveTextContent('Détail')

    act(() => vi.advanceTimersByTime(8000))
    expect(region).toBeEmptyDOMElement()
  })

  it('can be closed by hand', async () => {
    const user = userEvent.setup()
    render(
      <NotificationsProvider>
        <Trigger />
      </NotificationsProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Notifier' }))
    await user.click(screen.getByRole('button', { name: 'Fermer le message' }))

    expect(screen.queryByText('Message 1')).not.toBeInTheDocument()
  })

  it('keeps only the latest messages when many come at once', async () => {
    const user = userEvent.setup()
    render(
      <NotificationsProvider>
        <Trigger />
      </NotificationsProvider>,
    )

    for (let click = 0; click < 4; click++) await user.click(screen.getByRole('button', { name: 'Notifier' }))

    expect(screen.queryByText('Message 1')).not.toBeInTheDocument()
    expect(screen.getAllByText(/^Message/)).toHaveLength(3)
  })

  it('does nothing, and does not fail, outside its provider', async () => {
    const user = userEvent.setup()
    render(<Trigger />)

    await user.click(screen.getByRole('button', { name: 'Notifier' }))

    expect(screen.queryByText('Message 1')).not.toBeInTheDocument()
  })
})
