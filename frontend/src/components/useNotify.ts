import { createContext, useContext } from 'react'

export type Notification = {
  title: string
  /** A second, less prominent line. */
  detail?: string
}

// Outside the provider (a component rendered alone in a test), notifying does nothing.
export const NotifyContext = createContext<(notification: Notification) => void>(() => {})

/**
 * Shows a short message for a few seconds, from anywhere in the page:
 * something worth knowing happened, and nothing has to be done about it.
 */
export function useNotify(): (notification: Notification) => void {
  return useContext(NotifyContext)
}

