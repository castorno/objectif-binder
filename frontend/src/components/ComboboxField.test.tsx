import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { ComboboxField, type ComboboxOption } from './ComboboxField'

const SETS: ComboboxOption[] = [
  { value: 'ex3', label: 'EX Dragon', detail: 'nov. 2003 · ex3', keywords: 'ex3' },
  { value: 'sm7.5', label: 'Majesté des Dragons', detail: 'sept. 2018 · sm7.5', keywords: 'sm7.5' },
  { value: 'sv1', label: 'Écarlate et Violet', detail: 'mars 2023 · sv1', keywords: 'sv1' },
  { value: 'base1', label: 'Set de Base', detail: 'janv. 1999 · base1', keywords: 'base1' },
]

function renderField(initialValue = '', onSubmit = vi.fn()) {
  const onChange = vi.fn()

  function Field() {
    const [value, setValue] = useState(initialValue)

    return (
      <form
        onSubmit={(event) => {
          event.preventDefault()
          onSubmit()
        }}
      >
        <ComboboxField
          label="Extension"
          allLabel="Toutes les extensions"
          emptyMessage="Aucune extension ne correspond."
          value={value}
          options={SETS}
          onChange={(next) => {
            onChange(next)
            setValue(next)
          }}
        />
        <button type="submit">Chercher</button>
      </form>
    )
  }

  render(<Field />)

  return { user: userEvent.setup(), onChange, onSubmit, field: screen.getByRole('combobox', { name: 'Extension' }) }
}

function optionLabels(): string[] {
  return screen.getAllByRole('option').map((option) => option.textContent ?? '')
}

describe('ComboboxField', () => {
  it('keeps its list closed until the user asks for it', async () => {
    const { user, field } = renderField()

    expect(field).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByRole('option')).not.toBeInTheDocument()

    // Tabbing through a form must not pop lists open.
    await user.tab()
    expect(field).toHaveFocus()
    expect(field).toHaveAttribute('aria-expanded', 'false')

    await user.click(field)
    expect(field).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getAllByRole('option')).toHaveLength(5)
    expect(screen.getAllByRole('option')[0]).toHaveTextContent('Toutes les extensions')
  })

  it('narrows the list to what matches anywhere in a name', async () => {
    const { user, field } = renderField()

    await user.type(field, 'dragon')

    expect(optionLabels()).toEqual(['EX Dragon nov. 2003 · ex3', 'Majesté des Dragons sept. 2018 · sm7.5'])
    expect(screen.getByText('2 résultats')).toBeInTheDocument()
  })

  it('wants every typed word, ignores accents, and finds a set by its code', async () => {
    const { user, field } = renderField()

    await user.type(field, 'ex dragon')
    expect(screen.getAllByRole('option')).toHaveLength(1)
    expect(screen.getByRole('option')).toHaveTextContent('EX Dragon')

    await user.clear(field)
    await user.type(field, 'ecarlate')
    expect(screen.getByRole('option')).toHaveTextContent('Écarlate et Violet')

    await user.clear(field)
    await user.type(field, 'sm7.5')
    expect(screen.getByRole('option')).toHaveTextContent('Majesté des Dragons')
  })

  it('says so when nothing matches', async () => {
    const { user, field } = renderField()

    await user.type(field, 'licorne')

    expect(screen.queryByRole('option')).not.toBeInTheDocument()
    expect(screen.getAllByText('Aucune extension ne correspond.')).not.toHaveLength(0)
  })

  it('picks an option with the keyboard alone', async () => {
    const { user, field, onChange, onSubmit } = renderField()

    await user.type(field, 'dragon')
    // The first match is highlighted, and announced as such.
    expect(field).toHaveAttribute('aria-activedescendant', screen.getAllByRole('option')[0].id)

    await user.keyboard('{ArrowDown}')
    expect(field).toHaveAttribute('aria-activedescendant', screen.getAllByRole('option')[1].id)
    // The highlight stops at the end of the list.
    await user.keyboard('{ArrowDown}')
    expect(field).toHaveAttribute('aria-activedescendant', screen.getAllByRole('option')[1].id)

    await user.keyboard('{Enter}')

    expect(onChange).toHaveBeenCalledExactlyOnceWith('sm7.5')
    expect(field).toHaveValue('Majesté des Dragons')
    expect(field).toHaveAttribute('aria-expanded', 'false')
    expect(field).toHaveFocus()
    // Enter picked the option: it did not also submit the form around it.
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('lets Enter submit the form when the list is closed', async () => {
    const { user, field, onSubmit } = renderField('ex3')

    await user.click(field)
    await user.keyboard('{Escape}{Enter}')

    expect(onSubmit).toHaveBeenCalledOnce()
  })

  it('opens with the arrow keys, starting from the chosen option', async () => {
    const { user, field } = renderField('sv1')

    await user.tab()
    await user.keyboard('{ArrowDown}')

    const chosen = screen.getByRole('option', { selected: true })
    expect(chosen).toHaveTextContent('Écarlate et Violet')
    expect(field).toHaveAttribute('aria-activedescendant', chosen.id)
  })

  it('picks an option with the mouse', async () => {
    const { user, field, onChange } = renderField()

    await user.click(field)
    await user.click(screen.getByRole('option', { name: /Set de Base/ }))

    expect(onChange).toHaveBeenCalledExactlyOnceWith('base1')
    expect(field).toHaveValue('Set de Base')
  })

  it('changes nothing when the user types and leaves without choosing', async () => {
    const { user, field, onChange } = renderField('ex3')

    await user.clear(field)
    await user.type(field, 'base')
    await user.keyboard('{Escape}')
    expect(field).toHaveValue('EX Dragon')

    await user.type(field, 'x')
    await user.tab()

    expect(field).toHaveValue('EX Dragon')
    expect(onChange).not.toHaveBeenCalled()
  })

  it('goes back to every option through the first one or the clear button', async () => {
    const { user, field, onChange } = renderField('ex3')

    await user.click(screen.getByRole('button', { name: 'Effacer : Extension' }))
    expect(onChange).toHaveBeenLastCalledWith('')
    expect(field).toHaveValue('')
    // Nothing chosen: nothing to clear.
    expect(screen.queryByRole('button', { name: 'Effacer : Extension' })).not.toBeInTheDocument()

    await user.click(field)
    await user.click(screen.getByRole('option', { name: /EX Dragon/ }))
    await user.click(field)
    await user.click(screen.getByRole('option', { name: 'Toutes les extensions' }))
    expect(onChange).toHaveBeenLastCalledWith('')
  })

  it('shows a value it has no option for, rather than looking unset', () => {
    const { field } = renderField('unknown-set')

    expect(field).toHaveValue('unknown-set')
  })
})
