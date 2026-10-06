import type { ReactNode } from 'react'

type StateMessageProps = {
  title: string
  children?: ReactNode
  tone?: 'neutral' | 'danger'
  action?: ReactNode
}

/** Shared block for empty, error and not-found states. */
export function StateMessage({ title, children, tone = 'neutral', action }: StateMessageProps) {
  return (
    <div
      role={tone === 'danger' ? 'alert' : undefined}
      className={`rounded-xl border px-6 py-10 text-center ${
        tone === 'danger' ? 'border-danger/30 bg-danger-soft' : 'border-dashed border-line bg-surface'
      }`}
    >
      <h2 className={`text-lg font-semibold ${tone === 'danger' ? 'text-danger' : ''}`}>{title}</h2>
      {children && <div className="mx-auto mt-2 max-w-md text-sm text-muted">{children}</div>}
      {action && <div className="mt-5 flex justify-center">{action}</div>}
    </div>
  )
}
