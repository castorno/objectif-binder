import { useId, type ComponentProps, type ReactNode } from 'react'

type TextFieldProps = Omit<ComponentProps<'input'>, 'id' | 'aria-invalid' | 'aria-describedby'> & {
  label: string
  /** Shown under the field and tied to it for assistive technologies. */
  error?: string
  hint?: string
  /** Control placed inside the field, on the right (e.g. a show/hide toggle). */
  trailing?: ReactNode
}

export function TextField({ label, error, hint, trailing, className = '', ...input }: TextFieldProps) {
  const id = useId()
  const hintId = useId()
  const errorId = useId()
  const describedBy = [hint && hintId, error && errorId].filter(Boolean).join(' ') || undefined

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium">
        {label}
      </label>
      <div className="relative">
        <input
          id={id}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          className={`h-10 w-full rounded-lg border bg-surface px-3 text-sm placeholder:text-muted ${
            error ? 'border-danger' : 'border-line'
          } ${trailing ? 'pr-24' : ''} ${className}`}
          {...input}
        />
        {trailing && <div className="absolute inset-y-0 right-1 flex items-center">{trailing}</div>}
      </div>
      {hint && (
        <p id={hintId} className="text-sm text-muted">
          {hint}
        </p>
      )}
      {error && (
        <p id={errorId} className="text-sm text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
