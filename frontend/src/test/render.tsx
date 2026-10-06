import { QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { createQueryClient } from '../api/queryClient'
import { routes } from '../router'

/**
 * Mounts the whole application on `url`, with the real routes but an
 * in-memory history and a fresh query cache, so tests cannot leak into each
 * other. Returns the router, to read the current URL, and a `user` to
 * interact with the page.
 */
export function renderApp(url = '/') {
  const router = createMemoryRouter(routes, { initialEntries: [url] })
  // The application's own client, minus the retries: an error test would
  // otherwise wait through the backoff delays. The retry rule itself is
  // covered in api/queryClient.test.ts.
  const queryClient = createQueryClient()
  queryClient.setDefaultOptions({ queries: { retry: false } })

  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )

  return { router, user: userEvent.setup() }
}
