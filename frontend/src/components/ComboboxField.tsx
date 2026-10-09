import { useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react'
import { matchesSearch } from '../lib/textSearch'

export type ComboboxOption = {
  value: string
  label: string
  /** Shown next to the label, less prominently: a date, a code. */
  detail?: string
  /** A sign after the label. It must say what it means in words too, for those who do not see it. */
  mark?: ReactNode
  /** More text the option can be found by, beyond its label. */
  keywords?: string
}

type ComboboxFieldProps = {
  label: string
  value: string
  onChange: (value: string) => void
  options: ComboboxOption[]
  /** What the field stands for when nothing is chosen, e.g. "Toutes les extensions". */
  allLabel: string
  /** Said when the typed text matches no option. */
  emptyMessage: string
  disabled?: boolean
  /** Id of an element explaining the field, e.g. why it is disabled. */
  describedBy?: string
}

/**
 * A field to pick one option of a long list by typing part of its name: the
 * list narrows as the user types. For a short list, SelectField does the job
 * with a native control.
 *
 * Follows the WAI-ARIA "combobox with list autocomplete" pattern: the focus
 * stays in the text field, the arrow keys move a highlight through the list
 * (announced through aria-activedescendant), Enter picks the highlighted
 * option and Escape closes the list. Nothing is picked without an explicit
 * choice: typing then leaving the field leaves the value as it was.
 */
export function ComboboxField({
  label,
  value,
  onChange,
  options,
  allLabel,
  emptyMessage,
  disabled = false,
  describedBy,
}: ComboboxFieldProps) {
  const inputId = useId()
  const listId = useId()
  const optionIdPrefix = useId()
  const list = useRef<HTMLUListElement>(null)

  const [isOpen, setIsOpen] = useState(false)
  // What the user is typing; null when they are not, and the field shows the chosen option.
  const [draft, setDraft] = useState<string | null>(null)
  const [activeIndex, setActiveIndex] = useState(0)

  const selected = options.find((option) => option.value === value)
  // The value comes from the URL: while the options load, or if it matches
  // none, show it as it is rather than an empty field that would look unset.
  const selectedLabel = value === '' ? '' : (selected?.label ?? value)

  // "All" comes first, and only when the list is not being narrowed.
  const choices: ComboboxOption[] =
    draft === null || draft.trim() === ''
      ? [{ value: '', label: allLabel }, ...options]
      : options.filter((option) => matchesSearch(`${option.label} ${option.keywords ?? ''}`, draft))

  const active = isOpen ? choices[Math.min(activeIndex, choices.length - 1)] : undefined
  const optionId = (index: number) => `${optionIdPrefix}-${index}`

  // Keep the highlighted option in sight when the arrow keys walk a long list.
  useEffect(() => {
    if (isOpen) list.current?.querySelector('[data-active="true"]')?.scrollIntoView?.({ block: 'nearest' })
  }, [isOpen, activeIndex])

  function open() {
    if (isOpen) return

    setIsOpen(true)
    // Start from the chosen option, so the arrows continue from where the user is.
    setActiveIndex(Math.max(0, choices.findIndex((choice) => choice.value === value)))
  }

  function close() {
    setIsOpen(false)
    setDraft(null)
  }

  function choose(option: ComboboxOption) {
    close()
    if (option.value !== value) onChange(option.value)
  }

  function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        if (isOpen) setActiveIndex((index) => Math.min(index + 1, choices.length - 1))
        else open()
        break
      case 'ArrowUp':
        event.preventDefault()
        if (isOpen) setActiveIndex((index) => Math.max(index - 1, 0))
        else open()
        break
      case 'Enter':
        // With the list closed, Enter keeps its usual meaning: submit the form.
        if (active !== undefined) {
          event.preventDefault()
          choose(active)
        }
        break
      case 'Escape':
        if (isOpen) {
          event.preventDefault()
          close()
        }
        break
    }
  }

  return (
    <div className="relative flex flex-col gap-1.5">
      <label htmlFor={inputId} className="text-sm font-medium">
        {label}
      </label>
      <div className="relative">
        <input
          id={inputId}
          type="text"
          role="combobox"
          aria-expanded={isOpen}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-activedescendant={active === undefined ? undefined : optionId(choices.indexOf(active))}
          aria-describedby={describedBy}
          autoComplete="off"
          disabled={disabled}
          value={draft ?? selectedLabel}
          placeholder={allLabel}
          onChange={(event) => {
            setDraft(event.target.value)
            setIsOpen(true)
            setActiveIndex(0)
          }}
          onClick={open}
          onKeyDown={onKeyDown}
          onBlur={close}
          className={`h-10 w-full rounded-lg border border-line bg-surface pl-3 text-sm placeholder:text-ink disabled:cursor-not-allowed disabled:bg-sunken disabled:text-muted disabled:placeholder:text-muted ${
            value === '' ? 'pr-3' : 'pr-9'
          }`}
        />
        {value !== '' && !disabled && (
          <button
            type="button"
            aria-label={`Effacer : ${label}`}
            // Keeps the focus where it is: the click must not blur the field first.
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => {
              close()
              onChange('')
            }}
            className="absolute top-1 right-1 flex h-8 w-8 items-center justify-center rounded-md text-muted hover:bg-sunken hover:text-ink"
          >
            <svg aria-hidden="true" viewBox="0 0 16 16" className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
              <path d="M3 3l10 10M13 3L3 13" />
            </svg>
          </button>
        )}
      </div>

      <ul
        ref={list}
        id={listId}
        role="listbox"
        aria-label={label}
        hidden={!isOpen}
        // As wide as its longest option, within reason: a narrow field must
        // not break every name over several lines.
        className="absolute top-full left-0 z-20 mt-1 max-h-72 w-max max-w-[min(26rem,calc(100vw-2rem))] min-w-full overflow-y-auto rounded-lg border border-line bg-surface py-1 shadow-card"
      >
        {choices.map((choice, index) => {
          const isActive = choice === active

          return (
            <li
              key={choice.value}
              id={optionId(index)}
              role="option"
              aria-selected={choice.value === value}
              data-active={isActive}
              // The field keeps the focus: a click on an option must not blur it,
              // which would close the list before the click lands.
              onMouseDown={(event) => event.preventDefault()}
              onClick={() => choose(choice)}
              onMouseMove={() => setActiveIndex(index)}
              className={`flex cursor-pointer items-baseline justify-between gap-3 px-3 py-2 text-sm ${
                isActive ? 'bg-sunken' : ''
              } ${choice.value === value ? 'font-medium' : ''}`}
            >
              <span>
                {choice.label}
                {/* Kept with the last word of the label, wherever it wraps. */}
                {choice.mark !== undefined && <>&nbsp;{choice.mark}</>}
              </span>{' '}
              {choice.detail !== undefined && choice.detail !== '' && (
                <span className="shrink-0 text-xs whitespace-nowrap text-muted">{choice.detail}</span>
              )}
            </li>
          )
        })}
        {choices.length === 0 && (
          <li role="presentation" className="px-3 py-2 text-sm text-muted">
            {emptyMessage}
          </li>
        )}
      </ul>

      {/* A screen reader does not see the list change: say how much is left
          of it. A live region rather than role="status": the page around
          may have its own status, and this one is only about this field. */}
      <p aria-live="polite" className="sr-only">
        {isOpen && draft !== null && draft.trim() !== ''
          ? choices.length === 0
            ? emptyMessage
            : `${choices.length} ${choices.length > 1 ? 'résultats' : 'résultat'}`
          : ''}
      </p>
    </div>
  )
}
