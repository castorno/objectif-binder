import { useRef, useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router'
import { ApiError } from '../../api/client'
import { buttonStyles } from '../../components/buttonStyles'
import { TextField } from '../../components/TextField'
import { authLinkClasses, AuthLayout, FormMessage } from './AuthLayout'
import { useDestination } from './destination'
import { PasswordField } from './PasswordField'
import { SignInAfterRegistrationError, useRegister, useSession } from './useSession'

// Same bounds as the API (RegisterRequest), checked here to spare a round trip.
const MIN_PASSWORD_LENGTH = 10
const MAX_PASSWORD_LENGTH = 128

type FieldErrors = { email?: string; password?: string }

const INVALID_EMAIL = 'Saisissez une adresse e-mail valide.'
const WEAK_PASSWORD = `Choisissez un mot de passe plus long ou moins prévisible (${MIN_PASSWORD_LENGTH} caractères minimum).`

/**
 * What the API refused, field by field. Its own messages are in English and
 * tied to its validation library, so only the field at fault is read from
 * the answer; the wording is ours.
 */
function fieldErrorsFrom(error: Error): FieldErrors {
  if (!(error instanceof ApiError)) return {}
  if (error.status === 409) return { email: 'Un compte existe déjà avec cette adresse e-mail.' }
  if (error.status !== 422) return {}

  return {
    email: 'email' in error.violations ? INVALID_EMAIL : undefined,
    password: 'password' in error.violations ? WEAK_PASSWORD : undefined,
  }
}

function formErrorMessage(error: Error): string {
  if (error instanceof ApiError && error.status === 429) {
    return 'Trop de comptes ont été créés depuis cette connexion. Réessayez plus tard.'
  }

  return 'Inscription impossible pour le moment. Réessayez dans un instant.'
}

export function RegisterPage() {
  const { user } = useSession()
  const register = useRegister()
  const destination = useDestination()
  const navigate = useNavigate()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const emailRef = useRef<HTMLInputElement>(null)
  const passwordRef = useRef<HTMLInputElement>(null)

  // Signed in already, or just now: there is nothing to do here.
  if (user !== null) return <Navigate to={destination} replace />

  function show(found: FieldErrors) {
    setErrors(found)
    if (found.email) emailRef.current?.focus()
    else if (found.password) passwordRef.current?.focus()
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()

    const found: FieldErrors = {}
    if (email.trim() === '') found.email = 'Saisissez votre adresse e-mail.'
    if (password.length < MIN_PASSWORD_LENGTH) {
      found.password = `Le mot de passe doit contenir au moins ${MIN_PASSWORD_LENGTH} caractères.`
    } else if (password.length > MAX_PASSWORD_LENGTH) {
      found.password = `Le mot de passe ne peut pas dépasser ${MAX_PASSWORD_LENGTH} caractères.`
    }
    show(found)
    if (found.email || found.password) return

    register.mutate(
      { email: email.trim(), password },
      {
        onError: (error) => {
          if (error instanceof SignInAfterRegistrationError) {
            // The account exists; only signing in failed. Starting over here
            // would answer "this e-mail is already registered".
            void navigate('/login', { replace: true, state: { from: destination, accountCreated: true } })
          } else {
            show(fieldErrorsFrom(error))
          }
        },
      },
    )
  }

  // A refusal explained under a field needs no banner on top of it.
  const formError = register.isError && !errors.email && !errors.password ? formErrorMessage(register.error) : null

  return (
    <AuthLayout
      title="Créer un compte"
      intro="Pour suivre votre collection de cartes."
      onSubmit={handleSubmit}
      footer={
        <>
          Déjà un compte ?{' '}
          <Link to="/login" state={{ from: destination }} className={authLinkClasses}>
            Se connecter
          </Link>
        </>
      }
    >
      {formError && <FormMessage tone="danger">{formError}</FormMessage>}

      <TextField
        ref={emailRef}
        label="Adresse e-mail"
        type="email"
        name="email"
        autoComplete="email"
        required
        value={email}
        error={errors.email}
        onChange={(event) => setEmail(event.target.value)}
      />
      <PasswordField
        ref={passwordRef}
        label="Mot de passe"
        name="password"
        autoComplete="new-password"
        required
        value={password}
        error={errors.password}
        hint={`${MIN_PASSWORD_LENGTH} caractères minimum. Une phrase de plusieurs mots fait un bon mot de passe.`}
        onChange={(event) => setPassword(event.target.value)}
      />

      <button type="submit" disabled={register.isPending} className={`${buttonStyles.primary} h-10 disabled:opacity-60`}>
        {register.isPending ? 'Création du compte…' : 'Créer mon compte'}
      </button>
    </AuthLayout>
  )
}
