import { Link } from 'react-router-dom'
import { assetUrl } from '../api/client'
import type { ProductCard as Product } from '../api/types'
import { formatPrice, titleCase } from '../utils/format'

/** Card used everywhere a product is listed. Shows the cheapest in-stock offer ("From €X"). */
export default function ProductCard({ product: p }: { product: Product }) {
  return (
    <Link to={`/product/${p.slug}`} className={`card ${p.in_stock ? '' : 'card--soldout'}`}>
      <div className="card__image">
        <img src={assetUrl(p.cover)} alt={p.name} loading="lazy" />
        {p.discount > 0 && <span className="card__discount">-{p.discount}%</span>}
      </div>
      <div className="card__body">
        <div className="card__tags">
          <span className="tag">{p.platform}</span>
          <span className="tag tag--region">{titleCase(p.region)}</span>
        </div>
        <h3 className="card__title">{p.name}</h3>
        <div className="card__price">
          {p.in_stock ? (
            <>
              <span className="muted small">From</span>
              {p.discount > 0 && <s className="muted">{formatPrice(p.original_price)}</s>}
              <strong>{formatPrice(p.min_price)}</strong>
            </>
          ) : (
            <strong className="muted">Sold out</strong>
          )}
        </div>
        {p.offer_count > 1 && <span className="card__offers">{p.offer_count} sellers</span>}
      </div>
    </Link>
  )
}
