import { createBrowserRouter, type RouteObject } from 'react-router'
import { AppLayout } from './components/AppLayout'
import { NotFoundPage } from './components/NotFoundPage'
import { PullRatesPage } from './features/admin/PullRatesPage'
import { RequireAdmin } from './features/admin/RequireAdmin'
import { AccountPage } from './features/auth/AccountPage'
import { LoginPage } from './features/auth/LoginPage'
import { RegisterPage } from './features/auth/RegisterPage'
import { RequireAuth } from './features/auth/RequireAuth'
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
      { path: 'register', element: <RegisterPage /> },
      // Pages for signed-in users only.
      {
        element: <RequireAuth />,
        children: [{ path: 'account', element: <AccountPage /> }],
      },
      // Pages for administrators only.
      {
        element: <RequireAdmin />,
        children: [{ path: 'admin/pull-rates', element: <PullRatesPage /> }],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]

export const router = createBrowserRouter(routes)
