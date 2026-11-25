import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api } from '../api/client'
import type { Filters, Paginated, ProductCard as Product } from '../api/types'
import ProductCard from '../components/ProductCard'
import { CardSkeletons, Empty, ErrorBox } from '../components/StateViews'
import { useApi } from '../hooks/useApi'
import { useDebounce } from '../hooks/useDebounce'
import { titleCase } from '../utils/format'

const SORTS = [
  { value: '', label: 'Best match / Popular' },
  { value: 'popular', label: 'Most popular' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
  { value: 'discount', label: 'Biggest discount' },
  { value: 'newest', label: 'Newest' },
  { value: 'rating', label: 'Top rated' },
  { value: 'name', label: 'Name A–Z' },
]

/**
 * Store / search results.
 * The URL query string (?q=&platform=Steam,Xbox&sort=…) is the single source of truth for filters:
 * links and the back button work, and every change triggers ONE request to GET /api/products,
 * where the filtering, sorting and pagination happen in SQL.
 */
export default function StorePage() {
  const [params, setParams] = useSearchParams()
  const [filtersOpen, setFiltersOpen] = useState(false)

  const facets = useApi((signal) => api<{ data: Filters }>('/api/filters', { signal }).then((r) => r.data), [])
  const queryString = params.toString()
  const results = useApi(
    (signal) => api<Paginated<Product>>(`/api/products?${queryString}`, { signal }),
    [queryString],
  )

  /** Update one or more URL params; any filter change resets to page 1. */
  const setParam = (changes: Record<string, string | null>) => {
    const next = new URLSearchParams(params)
    for (const [k, v] of Object.entries(changes)) {
      if (v === null || v === '') next.delete(k)
      else next.set(k, v)
    }
    if (!('page' in changes)) next.delete('page')
    setParams(next)
  }

  const listValue = (key: string) => (params.get(key) ?? '').split(',').filter(Boolean)
  const toggle = (key: string, value: string) => {
    const current = listValue(key)
    const next = current.includes(value) ? current.filter((v) => v !== value) : [...current, value]
    setParam({ [key]: next.join(',') || null })
  }

  const q = params.get('q') ?? ''
  const meta = results.data?.meta
  const activeCount = ['category', 'platform', 'region'].reduce((n, k) => n + listValue(k).length, 0) +
    (params.get('price_min') ? 1 : 0) + (params.get('price_max') ? 1 : 0) + (params.get('in_stock') ? 1 : 0)

  return (
    <div className="container store">
      <div className="store__head">
        <div>
          <h1>{q ? <>Results for “{q}”</> : 'All products'}</h1>
          <p className="muted">{meta ? `${meta.total} product${meta.total === 1 ? '' : 's'} found` : ' '}</p>
        </div>
        <div className="store__controls">
          <button className="btn btn--ghost store__filter-toggle" onClick={() => setFiltersOpen(true)}>
            Filters{activeCount > 0 && ` (${activeCount})`}
          </button>
          <label className="select">
            <span className="sr-only">Sort by</span>
            <select value={params.get('sort') ?? ''} onChange={(e) => setParam({ sort: e.target.value || null })}>
              {SORTS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
            </select>
          </label>
        </div>
      </div>

      <div className="store__layout">
        <aside className={`filters ${filtersOpen ? 'is-open' : ''}`} aria-label="Filters">
          <div className="filters__head">
            <h2>Filters</h2>
            <button className="icon-btn filters__close" onClick={() => setFiltersOpen(false)} aria-label="Close filters">✕</button>
          </div>

          <SearchWithin value={q} onChange={(v) => setParam({ q: v || null })} />

          {facets.error && <ErrorBox error={facets.error} onRetry={facets.retry} />}
          {facets.data && (
            <>
              <FilterGroup title="Category" options={facets.data.categories.map((c) => ({ value: c.value, label: c.label ?? c.value, count: c.count }))}
                selected={listValue('category')} onToggle={(v) => toggle('category', v)} />
              <FilterGroup title="Platform" options={facets.data.platforms.map((c) => ({ value: c.value, label: c.value, count: c.count }))}
                selected={listValue('platform')} onToggle={(v) => toggle('platform', v)} />
              <FilterGroup title="Region" options={facets.data.regions.map((c) => ({ value: c.value, label: titleCase(c.value), count: c.count }))}
                selected={listValue('region')} onToggle={(v) => toggle('region', v)} />
              <PriceFilter
                min={params.get('price_min')}
                max={params.get('price_max')}
                onChange={(min, max) => setParam({ price_min: min, price_max: max })}
              />
            </>
          )}

          <label className="check">
            <input type="checkbox" checked={params.get('in_stock') === '1'} onChange={(e) => setParam({ in_stock: e.target.checked ? '1' : null })} />
            Only in stock
          </label>

          {activeCount > 0 && (
            <button className="btn btn--ghost btn--block" onClick={() => setParams(q ? { q } : {})}>Clear filters</button>
          )}
          <button className="btn btn--accent btn--block filters__done" onClick={() => setFiltersOpen(false)}>
            Show {meta?.total ?? ''} results
          </button>
        </aside>
        {filtersOpen && <div className="backdrop" onClick={() => setFiltersOpen(false)} />}

        <div className="store__results">
          {results.error ? (
            <ErrorBox error={results.error} onRetry={results.retry} />
          ) : results.data && results.data.data.length === 0 && !results.loading ? (
            <Empty title="No products match">
              <p className="muted">Try a different spelling or remove some filters.</p>
            </Empty>
          ) : (
            <div className={`grid ${results.loading ? 'is-loading' : ''}`}>
              {results.data ? results.data.data.map((p) => <ProductCard key={p.id} product={p} />) : <CardSkeletons count={8} />}
            </div>
          )}

          {meta && meta.last_page > 1 && (
            <nav className="pagination" aria-label="Pages">
              <button className="btn btn--ghost" disabled={meta.page <= 1} onClick={() => setParam({ page: String(meta.page - 1) })}>← Prev</button>
              <span>Page {meta.page} of {meta.last_page}</span>
              <button className="btn btn--ghost" disabled={meta.page >= meta.last_page} onClick={() => setParam({ page: String(meta.page + 1) })}>Next →</button>
            </nav>
          )}
        </div>
      </div>
    </div>
  )
}

function FilterGroup(props: {
  title: string
  options: { value: string; label: string; count: number }[]
  selected: string[]
  onToggle: (value: string) => void
}) {
  return (
    <fieldset className="filter-group">
      <legend>{props.title}</legend>
      {props.options.map((o) => (
        <label key={o.value} className="check">
          <input type="checkbox" checked={props.selected.includes(o.value)} onChange={() => props.onToggle(o.value)} />
          <span>{o.label}</span>
          <span className="check__count">{o.count}</span>
        </label>
      ))}
    </fieldset>
  )
}

/** Search box inside the filters; debounced so typing doesn't fire a request per keystroke. */
function SearchWithin({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const [text, setText] = useState(value)
  const debounced = useDebounce(text.trim(), 400)
  // Keep the box in sync when the query changes from outside (header search, "Clear filters").
  useEffect(() => {
    if (value !== text.trim()) setText(value)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value])
  useEffect(() => {
    if (debounced !== value) onChange(debounced)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debounced])
  return (
    <fieldset className="filter-group">
      <legend>Search</legend>
      <input className="input" type="search" placeholder="Product name…" value={text} onChange={(e) => setText(e.target.value)} />
    </fieldset>
  )
}

/** Price range in euros on screen, sent to the API in cents. Applied 500 ms after the user stops typing. */
function PriceFilter({ min, max, onChange }: { min: string | null; max: string | null; onChange: (min: string | null, max: string | null) => void }) {
  const toEuro = (cents: string | null) => (cents ? String(Number(cents) / 100) : '')
  const [lo, setLo] = useState(toEuro(min))
  const [hi, setHi] = useState(toEuro(max))
  const debounced = useDebounce(`${lo}|${hi}`, 500)

  // Reset the inputs when the URL changes from outside (e.g. "Clear filters").
  useEffect(() => {
    if (!min && !max) {
      setLo('')
      setHi('')
    }
  }, [min, max])

  useEffect(() => {
    const [a, b] = debounced.split('|')
    const toCents = (s: string) => (s && Number(s) >= 0 ? String(Math.round(Number(s) * 100)) : null)
    if (toCents(a) !== min || toCents(b) !== max) onChange(toCents(a), toCents(b))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debounced])

  return (
    <fieldset className="filter-group">
      <legend>Price (€)</legend>
      <div className="price-range">
        <input className="input" type="number" min="0" step="1" inputMode="decimal" placeholder="Min" value={lo} onChange={(e) => setLo(e.target.value)} aria-label="Minimum price" />
        <span>–</span>
        <input className="input" type="number" min="0" step="1" inputMode="decimal" placeholder="Max" value={hi} onChange={(e) => setHi(e.target.value)} aria-label="Maximum price" />
      </div>
    </fieldset>
  )
}
