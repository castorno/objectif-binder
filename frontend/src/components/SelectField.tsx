import { useId } from 'react'

type Option = { value: string; label: string }

type SelectFieldProps = {
  label: string
  value: string
  onChange: (value: string) => void
  options: Option[]
  /** Label of the empty option, e.g. "Toutes les extensions". */
  allLabel: string
  disabled?: boolean
  /** Id of an element explaining the field, e.g. why it is disabled. */
  describedBy?: string
}

export function SelectField({ label, value, onChange, options, allLabel, disabled = false, describedBy }: SelectFieldProps) {
  const id = useId()
  // The value comes from the URL: keep it selectable while options load, or
  // if it matches nothing, rather than silently displaying another option.
  const hasUnknownValue = value !== '' && !options.some((option) => option.value === value)

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium">
        {label}
      </label>
      <select
        id={id}
        value={value}
        disabled={disabled}
        aria-describedby={describedBy}
        onChange={(event) => onChange(event.target.value)}
        className="h-10 rounded-lg border border-line bg-surface px-3 text-sm disabled:cursor-not-allowed disabled:bg-sunken disabled:text-muted"
      >
        <option value="">{allLabel}</option>
        {hasUnknownValue && <option value={value}>{value}</option>}
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </div>
  )
}
