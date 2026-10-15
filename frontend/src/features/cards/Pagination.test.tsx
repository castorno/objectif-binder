import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { describe, expect, it, vi } from 'vitest'
import { Pagination } from './Pagination'

function renderPagination(page: number, totalPages: number, { total = totalPages * 20, onPageSizeChange = () => {} } = {}) {
  render(
    <MemoryRouter>
      <Pagination
        page={page}
        totalPages={totalPages}
        hrefForPage={(target) => `?game=demo&page=${target}`}
        total={total}
        pageSize={20}
        onPageSizeChange={onPageSizeChange}
      />
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

  it('links the two pages on each side of the current one', () => {
    renderPagination(6, 12)

    for (const page of [4, 5, 7, 8]) {
      expect(screen.getByRole('link', { name: `Page ${page}` })).toBeInTheDocument()
    }
    expect(screen.queryByRole('link', { name: 'Page 3' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Page 9' })).not.toBeInTheDocument()
  })

  it('offers a larger page, and says which size is picked', async () => {
    const onPageSizeChange = vi.fn()
    renderPagination(1, 3, { onPageSizeChange })

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Par page' }), '60')

    expect(onPageSizeChange).toHaveBeenCalledWith(60)
  })

  it('keeps the page size on offer when a larger page holds everything', () => {
    renderPagination(1, 1, { total: 50 })

    expect(screen.getByRole('combobox', { name: 'Par page' })).toBeInTheDocument()
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument()
  })

  it('offers no page size when the smallest page holds everything', () => {
    renderPagination(1, 1, { total: 20 })

    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })
})
