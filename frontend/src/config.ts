/**
 * Single place for the product name. The styled wordmark in AppLayout and the
 * static fallback <title> in index.html spell it out and must follow by hand.
 */
export const APP_NAME = 'Objectif Binder'

/** Browser tab title for a page, e.g. "Catalogue — Objectif Binder". */
export function pageTitle(section: string): string {
  return `${section} — ${APP_NAME}`
}
