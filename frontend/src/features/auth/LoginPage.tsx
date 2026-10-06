import { useRef, useState, type FormEvent } from 'react'
import { Navigate } from 'react-router'
import { ApiError } from '../../api/client'
import { buttonStyles } from '../../components/buttonStyles'
import { TextField } from '../../components/TextField'
import { pageTitle } from '../../config'
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

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const emailRef = useRef<HTMLInputElement>(null)
  const passwordRef = useRef<HTMLInputElement>(null)

  // Signed in already, or just now: there is nothing to do here.
  if (user !== null) return <Navigate to={destination} replace />

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
    <div className="mx-auto max-w-sm">
      <title>{pageTitle('Connexion')}</title>

      <h1 className="text-3xl font-semibold tracking-tight">Connexion</h1>
      <p className="mt-1 text-muted">Accédez à votre compte Objectif Binder.</p>

      {/* noValidate: the checks above give the same messages in every browser. */}
      <form noValidate onSubmit={handleSubmit} className="mt-6 flex flex-col gap-4 rounded-xl border border-line bg-surface p-5">
        {login.isError && (
          <p role="alert" className="rounded-lg border border-danger/30 bg-danger-soft px-3 py-2 text-sm text-danger">
            {loginErrorMessage(login.error)}
          </p>
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
      </form>
    </div>
  )
}
