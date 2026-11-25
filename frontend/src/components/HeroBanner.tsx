import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, assetUrl } from '../api/client'
import type { ProductCard } from '../api/types'
import { useApi } from '../hooks/useApi'
import { formatPrice } from '../utils/format'

/**
 * Rotating banner of featured products (GET /api/home/featured).
 * If the request fails we fall back to a static promo banner instead of an error, because the banner
 * is decoration and shouldn't break the page.
 */
export default function HeroBanner() {
  const { data } = useApi(
    (signal) => api<{ data: ProductCard[] }>('/api/home/featured', { signal }).then((r) => r.data),
    [],
  )
  const slides = data ?? []
  const [index, setIndex] = useState(0)

  useEffect(() => {
    if (slides.length < 2) return
    const id = setInterval(() => setIndex((i) => (i + 1) % slides.length), 6000)
    return () => clearInterval(id)
  }, [slides.length])

  const current = slides[index]

  return (
    <div className="hero-grid">
      <div className="hero">
        {current ? (
          <Link to={`/product/${current.slug}`} className="hero__slide" key={current.id}>
            <img className="hero__bg" src={assetUrl(current.cover)} alt="" aria-hidden="true" />
            <div className="hero__content">
              <span className="pill">Featured · {current.platform}</span>
              <h1>{current.name}</h1>
              <p>
                {current.discount > 0 && <span className="hero__discount">-{current.discount}%</span>}
                From <strong>{formatPrice(current.min_price)}</strong>
              </p>
              <span className="btn btn--accent">Buy now</span>
            </div>
            <img className="hero__cover" src={assetUrl(current.cover)} alt={current.name} />
          </Link>
        ) : (
          <div className="hero__slide hero__slide--static">
            <div className="hero__content">
              <span className="pill">Black Week</span>
              <h1>Game keys up to 70% off</h1>
              <p>Compare offers from verified sellers and get your key instantly.</p>
              <Link to="/store?sort=discount" className="btn btn--accent">Browse deals</Link>
            </div>
          </div>
        )}
        {slides.length > 1 && (
          <div className="hero__dots">
            {slides.map((s, i) => (
              <button key={s.id} className={i === index ? 'is-active' : ''} onClick={() => setIndex(i)} aria-label={`Show ${s.name}`} />
            ))}
          </div>
        )}
      </div>
      <div className="promo-stack">
        <Link to="/store?category=gift-cards" className="promo promo--green">
          <span className="pill">Top up</span>
          <h3>Gift cards &amp; subscriptions</h3>
          <span className="muted">Steam, PSN, Xbox, Nintendo</span>
        </Link>
        <Link to="/store?sort=newest" className="promo promo--yellow">
          <span className="pill">Just landed</span>
          <h3>New releases</h3>
          <span>The latest games, instant delivery</span>
        </Link>
      </div>
    </div>
  )
}
