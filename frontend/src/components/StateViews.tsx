import type { ReactNode } from 'react'
import type { ApiError } from '../api/client'

export function Spinner({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="spinner" role="status">
      <span className="spinner__circle" />
      <span className="sr-only">{label}</span>
    </div>
  )
}

export function ErrorBox({ error, onRetry }: { error: ApiError | Error; onRetry?: () => void }) {
  return (
    <div className="error-box" role="alert">
      <span>⚠ {error.message}</span>
      {onRetry && (
        <button className="btn btn--small btn--ghost" onClick={onRetry}>
          Try again
        </button>
      )}
    </div>
  )
}

export function Empty({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="empty">
      <h3>{title}</h3>
      {children}
    </div>
  )
}

/** Grey placeholder cards while a product row loads. */
export function CardSkeletons({ count = 5 }: { count?: number }) {
  return (
    <>
      {Array.from({ length: count }, (_, i) => (
        <div key={i} className="card card--skeleton" aria-hidden="true">
          <div className="card__image" />
          <div className="card__body">
            <span className="skeleton-line" />
            <span className="skeleton-line skeleton-line--short" />
          </div>
        </div>
      ))}
    </>
  )
}
