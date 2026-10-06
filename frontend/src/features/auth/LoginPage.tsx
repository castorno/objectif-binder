import { useRef, useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation } from 'react-router'
import { ApiError } from '../../api/client'
import { buttonStyles } from '../../components/buttonStyles'
import { TextField } from '../../components/TextField'
import { authLinkClasses, AuthLayout, FormMessage } from './AuthLayout'
import { useDestination } from './destination'
import { PasswordField } from './PasswordField'
import { useLogin, useSession } from './useSession'

type FieldErrors = { email?: string; password?: string }

/**
 * Chosen from the status code, never from the API's own text: the wording
 * stays in French and under our control, and says no more than it should
 * (nothing tells a wrong password from an unknown e-mail).
 */
function loginErrorMessage(error: Error): string {
  if (error instanceof ApiError) {
    if (error.status === 401) return 'E-mail ou mot de passe incorrect.'
    if (error.status === 429) return 'Trop de tentatives. Patientez une minute avant de réessayer.'
  }

  return 'Connexion impossible pour le moment. Réessayez dans un instant.'
}

export function LoginPage() {
  const { user } = useSession()
  const login = useLogin()
  const destination = useDestination()
  const location = useLocation()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const emailRef = useRef<HTMLInputElement>(null)
  const passwordRef = useRef<HTMLInputElement>(null)

  // Signed in already, or just now: there is nothing to do here.
  if (user !== null) return <Navigate to={destination} replace />

  // Set by the sign-up screen when the account exists but signing in failed.
  const accountJustCreated = location.state?.accountCreated === true

  function handleSubmit(event: FormEvent) {
    event.preventDefault()

    const found: FieldErrors = {}
    if (email.trim() === '') found.email = 'Saisissez votre adresse e-mail.'
    if (password === '') found.password = 'Saisissez votre mot de passe.'
    setErrors(found)

    if (found.email) return emailRef.current?.focus()
    if (found.password) return passwordRef.current?.focus()

    login.mutate({ email: email.trim(), password })
  }

  return (
    <AuthLayout
      title="Connexion"
      intro="Accédez à votre compte Objectif Binder."
      onSubmit={handleSubmit}
      footer={
        <>
          Pas encore de compte ?{' '}
          {/* Carries along the page to come back to once signed in. */}
          <Link to="/register" state={{ from: destination }} className={authLinkClasses}>
            Créer un compte
          </Link>
        </>
      }
    >
      {login.isError ? (
        <FormMessage tone="danger">{loginErrorMessage(login.error)}</FormMessage>
      ) : (
        accountJustCreated && <FormMessage tone="neutral">Votre compte est créé. Connectez-vous pour continuer.</FormMessage>
      )}

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
        autoComplete="current-password"
        required
        value={password}
        error={errors.password}
        onChange={(event) => setPassword(event.target.value)}
      />

      <button type="submit" disabled={login.isPending} className={`${buttonStyles.primary} h-10 disabled:opacity-60`}>
        {login.isPending ? 'Connexion…' : 'Se connecter'}
      </button>
    </AuthLayout>
  )
}
