import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { describe, expect, it } from 'vitest'
import { Pagination } from './Pagination'

function renderPagination(page: number, totalPages: number) {
  render(
    <MemoryRouter>
      <Pagination page={page} totalPages={totalPages} hrefForPage={(target) => `?game=demo&page=${target}`} />
    </MemoryRouter>,
  )
}

describe('Pagination', () => {
  it('renders nothing when there is a single page', () => {
    renderPagination(1, 1)

    expect(screen.queryByRole('navigation')).not.toBeInTheDocument()
  })

  it('marks the current page for assistive technologies', () => {
    renderPagination(3, 5)

    expect(screen.getByRole('link', { name: 'Page 3' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Page 2' })).not.toHaveAttribute('aria-current')
  })

  it('links each page through hrefForPage, keeping the current filters', () => {
    renderPagination(3, 5)

    expect(screen.getByRole('link', { name: 'Page 4' })).toHaveAttribute('href', '/?game=demo&page=4')
    expect(screen.getByRole('link', { name: 'Précédent' })).toHaveAttribute('href', '/?game=demo&page=2')
    expect(screen.getByRole('link', { name: 'Suivant' })).toHaveAttribute('href', '/?game=demo&page=4')
  })

  it('disables "Précédent" on the first page', () => {
    renderPagination(1, 5)

    expect(screen.queryByRole('link', { name: 'Précédent' })).not.toBeInTheDocument()
    expect(screen.getByText('Précédent')).toHaveAttribute('aria-disabled', 'true')
    expect(screen.getByRole('link', { name: 'Suivant' })).toBeInTheDocument()
  })

  it('disables "Suivant" on the last page', () => {
    renderPagination(5, 5)

    expect(screen.queryByRole('link', { name: 'Suivant' })).not.toBeInTheDocument()
    expect(screen.getByText('Suivant')).toHaveAttribute('aria-disabled', 'true')
    expect(screen.getByRole('link', { name: 'Précédent' })).toBeInTheDocument()
  })

  it('states the position in words, for the compact layout without page links', () => {
    renderPagination(3, 5)

    expect(screen.getByText('Page 3 sur 5')).toBeInTheDocument()
  })
})
