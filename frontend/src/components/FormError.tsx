import type { ReactNode } from 'react'

type FormErrorProps = {
  children: ReactNode
  /** For a field to point at with aria-describedby. */
  id?: string
  /** Spacing from what comes before, e.g. "mt-2". */
  className?: string
}

/** What went wrong with a form or an action, read out as soon as it shows. */
export function FormError({ children, id, className = '' }: FormErrorProps) {
  return (
    <p id={id} role="alert" className={`${className} text-sm text-danger`.trim()}>
      {children}
    </p>
  )
}
