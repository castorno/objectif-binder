import { createBrowserRouter, type RouteObject } from 'react-router'
import { AppLayout } from './components/AppLayout'
import { NotFoundPage } from './components/NotFoundPage'
import { LoginPage } from './features/auth/LoginPage'
import { CardDetailPage } from './features/cards/CardDetailPage'
import { CardSearchPage } from './features/cards/CardSearchPage'

/** Exported apart from the router so tests can mount the same routes in memory. */
export const routes: RouteObject[] = [
  {
    element: <AppLayout />,
    children: [
      { index: true, element: <CardSearchPage /> },
      { path: 'cards/:id', element: <CardDetailPage /> },
      { path: 'login', element: <LoginPage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]

export const router = createBrowserRouter(routes)
