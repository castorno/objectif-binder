import '@testing-library/jest-dom/vitest'
import { cleanup, configure } from '@testing-library/react'
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

// How long findBy… and waitFor wait before giving up. The default, one
// second, is short for the first test of a file: it pays for loading the
// modules while every other file does the same in parallel, and would fail
// now and then for no reason of its own.
configure({ asyncUtilTimeout: 3000 })
