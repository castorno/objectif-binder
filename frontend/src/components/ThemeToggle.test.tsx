import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, test } from 'vitest'
import { ThemeToggle } from './ThemeToggle'

afterEach(() => {
  delete document.documentElement.dataset.theme
  localStorage.clear()
})

test('the site is dark until the visitor switches, and the choice is kept', async () => {
  const user = userEvent.setup()
  render(<ThemeToggle />)

  await user.click(screen.getByRole('button', { name: 'Passer au thème clair' }))

  expect(document.documentElement.dataset.theme).toBe('light')
  expect(localStorage.getItem('theme')).toBe('light')

  await user.click(screen.getByRole('button', { name: 'Passer au thème sombre' }))

  expect(document.documentElement.dataset.theme).toBeUndefined()
  expect(localStorage.getItem('theme')).toBe('dark')
})

test('the button starts from the theme the page was loaded with', () => {
  document.documentElement.dataset.theme = 'light'
  render(<ThemeToggle />)

  expect(screen.getByRole('button', { name: 'Passer au thème sombre' })).toBeInTheDocument()
})
