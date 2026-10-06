import { useState, type ComponentProps } from 'react'
import { TextField } from '../../components/TextField'

type PasswordFieldProps = Omit<ComponentProps<typeof TextField>, 'type' | 'trailing'>

/** Password input the user can reveal, to check what they typed. */
export function PasswordField(props: PasswordFieldProps) {
  const [visible, setVisible] = useState(false)

  return (
    <TextField
      {...props}
      type={visible ? 'text' : 'password'}
      trailing={
        <button
          type="button"
          aria-label="Afficher le mot de passe"
          aria-pressed={visible}
          onClick={() => setVisible((current) => !current)}
          className="rounded-md px-2 py-1 text-sm font-medium text-accent underline-offset-4 hover:underline"
        >
          {visible ? 'Masquer' : 'Afficher'}
        </button>
      }
    />
  )
}
