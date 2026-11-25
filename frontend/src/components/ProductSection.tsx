import { Link } from 'react-router-dom'
import { api } from '../api/client'
import type { ProductCard as Product } from '../api/types'
import { useApi } from '../hooks/useApi'
import ProductCard from './ProductCard'
import { CardSkeletons, ErrorBox } from './StateViews'

interface Props {
  section: 'popular' | 'deals' | 'new' | 'recommended'
  title: string
  subtitle?: string
  showAllLink: string
}

/**
 * One homepage row. Each row fetches its own endpoint (/api/home/{section}) and keeps its own
 * loading/error state, so if one request fails only that row shows an error, and it can be retried.
 */
export default function ProductSection({ section, title, subtitle, showAllLink }: Props) {
  const { data, error, loading, retry } = useApi(
    (signal) => api<{ data: Product[] }>(`/api/home/${section}`, { signal }).then((r) => r.data),
    [section],
  )

  return (
    <section className="section">
      <div className="section__head">
        <div>
          <h2>{title}</h2>
          {subtitle && <p className="muted">{subtitle}</p>}
        </div>
        <Link to={showAllLink} className="section__all">Show all →</Link>
      </div>
      {error ? (
        <ErrorBox error={error} onRetry={retry} />
      ) : (
        <div className="row-scroll">
          {loading || !data ? <CardSkeletons /> : data.map((p) => <ProductCard key={p.id} product={p} />)}
        </div>
      )}
    </section>
  )
}
