import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { routes } from '../router'

/**
 * Mounts the whole application on `url`, with the real routes but an
 * in-memory history and a fresh query cache, so tests cannot leak into each
 * other. Returns the router, to read the current URL, and a `user` to
 * interact with the page.
 */
export function renderApp(url = '/') {
  const router = createMemoryRouter(routes, { initialEntries: [url] })
  // No retry: an error test would otherwise wait through the backoff delays.
  // The retry rule itself is covered in api/queryClient.test.ts.
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )

  return { router, user: userEvent.setup() }
}
