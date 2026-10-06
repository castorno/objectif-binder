import type { FormEvent, ReactNode } from 'react'
import { pageTitle } from '../../config'

type AuthLayoutProps = {
  title: string
  intro: string
  onSubmit: (event: FormEvent) => void
  /** Fields and submit button. */
  children: ReactNode
  /** Shown under the form, e.g. the link to the other screen. */
  footer: ReactNode
}

/** Shared frame of the sign-in and sign-up screens. */
export function AuthLayout({ title, intro, onSubmit, children, footer }: AuthLayoutProps) {
  return (
    <div className="mx-auto max-w-sm">
      <title>{pageTitle(title)}</title>

      <h1 className="text-3xl font-semibold tracking-tight">{title}</h1>
      <p className="mt-1 text-muted">{intro}</p>

      {/* noValidate: each screen checks its fields itself, to give the same
          messages in every browser. */}
      <form noValidate onSubmit={onSubmit} className="mt-6 flex flex-col gap-4 rounded-xl border border-line bg-surface p-5">
        {children}
      </form>

      <p className="mt-4 text-center text-sm text-muted">{footer}</p>
    </div>
  )
}

/** A message about the whole form rather than one field. */
export function FormMessage({ tone, children }: { tone: 'danger' | 'neutral'; children: ReactNode }) {
  return (
    <p
      role={tone === 'danger' ? 'alert' : 'status'}
      className={`rounded-lg border px-3 py-2 text-sm ${
        tone === 'danger' ? 'border-danger/30 bg-danger-soft text-danger' : 'border-line bg-sunken'
      }`}
    >
      {children}
    </p>
  )
}

export const authLinkClasses = 'rounded-md font-medium text-accent underline-offset-4 hover:underline'
