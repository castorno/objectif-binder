import { useEffect, useState } from 'react'

type HealthResponse = {
  status: string
  database: string
}

type HealthState =
  | { kind: 'loading' }
  | { kind: 'success'; data: HealthResponse }
  | { kind: 'error'; message: string }

function useHealthCheck(): HealthState {
  const [state, setState] = useState<HealthState>({ kind: 'loading' })

  useEffect(() => {
    const apiUrl = import.meta.env.VITE_API_URL
    const controller = new AbortController()

    fetch(`${apiUrl}/api/health`, { signal: controller.signal })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`)
        }
        return response.json() as Promise<HealthResponse>
      })
      .then((data) => setState({ kind: 'success', data }))
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') return
        const message = error instanceof Error ? error.message : 'Erreur inconnue'
        setState({ kind: 'error', message })
      })

    return () => controller.abort()
  }, [])

  return state
}

function StatusBadge({ ok, label }: { ok: boolean; label: string }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-sm font-medium ${
        ok
          ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
          : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
      }`}
    >
      <span className={`h-2 w-2 rounded-full ${ok ? 'bg-green-500' : 'bg-red-500'}`} />
      {label}
    </span>
  )
}

function App() {
  const health = useHealthCheck()

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4 dark:bg-slate-950">
      <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h1 className="text-xl font-semibold text-slate-900 dark:text-slate-100">
          tcgCollector
        </h1>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Vérification de la connexion au backend
        </p>

        <div className="mt-6 space-y-3">
          {health.kind === 'loading' && (
            <p className="text-sm text-slate-500 dark:text-slate-400">
              Connexion à l'API…
            </p>
          )}

          {health.kind === 'success' && (
            <>
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-600 dark:text-slate-300">API</span>
                <StatusBadge ok={health.data.status === 'ok'} label={health.data.status} />
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-600 dark:text-slate-300">
                  Base de données
                </span>
                <StatusBadge
                  ok={health.data.database === 'ok'}
                  label={health.data.database}
                />
              </div>
            </>
          )}

          {health.kind === 'error' && (
            <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">
              Impossible de contacter l'API : {health.message}
            </div>
          )}
        </div>
      </div>
    </main>
  )
}

export default App
