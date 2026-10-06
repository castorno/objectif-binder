import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll } from 'vitest'
import { setAccessToken } from '../api/accessToken'
import { server } from './server'

// jsdom has no layout, so it does not implement scrolling and logs an error
// each time the router restores the scroll position after a navigation.
window.scrollTo = () => {}

// A request no handler expects is a bug in the test or in the app: fail
// loudly rather than let it reach the network.
beforeAll(() => server.listen({ onUnhandledFrame: 'error' }))

afterEach(() => {
  // Testing Library only unmounts automatically when the test globals are
  // enabled; tests here import them explicitly, so it has to be asked for.
  cleanup()
  server.resetHandlers()
  // The access token lives in a module variable: do not let it outlive a test.
  setAccessToken(null)
})

afterAll(() => server.close())
