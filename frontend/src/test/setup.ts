import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// Testing Library only unmounts automatically when the test globals are
// enabled; tests here import them explicitly, so it has to be asked for.
afterEach(cleanup)
