import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '../api/client'

interface State<T> {
  data: T | null
  error: ApiError | null
  loading: boolean
}

/**
 * Runs an async loader whenever `deps` change and tracks loading / error / data.
 * Each component that uses this has its OWN state, which is what keeps homepage sections
 * independent: one section's error doesn't affect the others.
 *
 * The loader gets an AbortSignal so outdated requests (e.g. the user changed a filter) are cancelled.
 */
export function useApi<T>(loader: (signal: AbortSignal) => Promise<T>, deps: unknown[]) {
  const [state, setState] = useState<State<T>>({ data: null, error: null, loading: true })
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    setState((s) => ({ ...s, loading: true, error: null }))
    loader(controller.signal)
      .then((data) => setState({ data, error: null, loading: false }))
      .catch((e: Error) => {
        if (e.name === 'AbortError') return
        setState({ data: null, loading: false, error: e instanceof ApiError ? e : new ApiError(0, e.message) })
      })
    return () => controller.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, attempt])

  const retry = useCallback(() => setAttempt((n) => n + 1), [])
  return { ...state, retry }
}
