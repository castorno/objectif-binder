export type Theme = 'dark' | 'light'

const STORAGE_KEY = 'theme'

/**
 * The theme in use, read from the page itself: a script in index.html sets it
 * before the first paint, so that a visitor who chose light never sees a dark
 * flash.
 */
export function currentTheme(): Theme {
  return document.documentElement.dataset.theme === 'light' ? 'light' : 'dark'
}

export function applyTheme(theme: Theme) {
  if (theme === 'light') {
    document.documentElement.dataset.theme = 'light'
  } else {
    delete document.documentElement.dataset.theme
  }

  try {
    localStorage.setItem(STORAGE_KEY, theme)
  } catch {
    // Storage refused (private window, blocked site data): the choice still
    // holds for this visit.
  }
}
