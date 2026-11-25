import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, assetUrl } from '../api/client'
import type { Suggestion } from '../api/types'
import { useDebounce } from '../hooks/useDebounce'
import { formatPrice } from '../utils/format'

/**
 * Header search with suggestions.
 * - The input updates instantly, but the request only goes out 300 ms after the user stops typing (useDebounce).
 * - An AbortController cancels a still-running request when a newer one starts, so old results never
 *   overwrite new ones.
 * - Enter (with nothing highlighted) opens the store with ?q=…; the backend does partial + typo-tolerant matching.
 */
export default function SearchBox({ onDone }: { onDone?: () => void }) {
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [items, setItems] = useState<Suggestion[]>([])
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(-1)
  const [loading, setLoading] = useState(false)
  const debounced = useDebounce(query.trim(), 300)
  const boxRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (debounced.length < 2) {
      setItems([])
      return
    }
    const controller = new AbortController()
    setLoading(true)
    api<{ data: Suggestion[] }>('/api/search/suggest', { query: { q: debounced }, signal: controller.signal })
      .then((r) => {
        setItems(r.data)
        setActive(-1)
      })
      .catch(() => {}) // suggestions are optional; ignore errors
      .finally(() => setLoading(false))
    return () => controller.abort()
  }, [debounced])

  // Close the dropdown when clicking outside.
  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (!boxRef.current?.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  const finish = (path: string) => {
    setOpen(false)
    setQuery('')
    navigate(path)
    onDone?.()
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (active >= 0 && items[active]) return finish(`/product/${items[active].slug}`)
    if (query.trim()) finish(`/store?q=${encodeURIComponent(query.trim())}`)
  }

  const onKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setActive((i) => Math.min(i + 1, items.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActive((i) => Math.max(i - 1, -1))
    } else if (e.key === 'Escape') {
      setOpen(false)
    }
  }

  const showDropdown = open && query.trim().length >= 2

  return (
    <div className="search" ref={boxRef}>
      <form onSubmit={submit} role="search">
        <svg className="search__icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M10.5 3a7.5 7.5 0 0 1 5.92 12.1l4.24 4.24-1.42 1.42-4.24-4.24A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z" />
        </svg>
        <input
          type="search"
          placeholder="Search games, gift cards…"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={onKeyDown}
          aria-label="Search products"
          aria-autocomplete="list"
          autoComplete="off"
        />
      </form>

      {showDropdown && (
        <div className="search__dropdown" role="listbox">
          {items.map((s, i) => (
            <button
              key={s.slug}
              type="button"
              role="option"
              aria-selected={i === active}
              className={`suggestion ${i === active ? 'is-active' : ''}`}
              onMouseEnter={() => setActive(i)}
              onClick={() => finish(`/product/${s.slug}`)}
            >
              <img src={assetUrl(s.cover)} alt="" />
              <span className="suggestion__text">
                <span className="suggestion__name">{s.name}</span>
                <span className="suggestion__meta">
                  {s.platform} · {s.region}
                </span>
              </span>
              <span className="suggestion__price">{s.min_price ? formatPrice(s.min_price) : 'Sold out'}</span>
            </button>
          ))}
          {!loading && items.length === 0 && debounced === query.trim() && (
            <div className="search__empty">No matches for “{query.trim()}”</div>
          )}
          <button type="button" className="search__all" onClick={() => finish(`/store?q=${encodeURIComponent(query.trim())}`)}>
            See all results for “{query.trim()}” →
          </button>
        </div>
      )}
    </div>
  )
}
