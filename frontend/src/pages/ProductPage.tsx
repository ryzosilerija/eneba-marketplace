import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError, assetUrl } from '../api/client'
import type { Offer, ProductDetail } from '../api/types'
import ProductCard from '../components/ProductCard'
import { Empty, ErrorBox, Spinner } from '../components/StateViews'
import { useCart } from '../context/CartContext'
import { useToast } from '../context/ToastContext'
import { useApi } from '../hooks/useApi'
import { formatDate, formatPrice, titleCase } from '../utils/format'

/**
 * Product page. A PRODUCT is the game itself (name, images, platform, region).
 * Below it is the list of OFFERS: each seller's own price and stock for that product.
 * "Add to cart" always adds a specific offer, so the cart knows which seller was picked.
 */
export default function ProductPage() {
  const { slug = '' } = useParams()
  const { data: p, error, loading, retry } = useApi(
    (signal) => api<{ data: ProductDetail }>(`/api/products/${encodeURIComponent(slug)}`, { signal }).then((r) => r.data),
    [slug],
  )
  const [imageIndex, setImageIndex] = useState(0)

  if (loading) return <Spinner />
  if (error?.status === 404) {
    return (
      <div className="container">
        <Empty title="Product not found"><Link to="/store" className="btn btn--accent">Back to store</Link></Empty>
      </div>
    )
  }
  if (error || !p) return <div className="container"><ErrorBox error={error ?? new Error('Not found')} onRetry={retry} /></div>

  const offers = [...p.offers].sort((a, b) => Number(b.in_stock) - Number(a.in_stock) || a.price - b.price)
  const best = offers.find((o) => o.in_stock)
  const image = p.images[imageIndex] ?? p.images[0]

  return (
    <div className="container product">
      <nav className="breadcrumbs">
        <Link to="/">Home</Link> / <Link to={`/store?category=${p.category.slug}`}>{p.category.name}</Link> / <span>{p.name}</span>
      </nav>

      <div className="product__top">
        <div className="gallery">
          <div className="gallery__main">
            <img src={assetUrl(image)} alt={p.name} />
          </div>
          {p.images.length > 1 && (
            <div className="gallery__thumbs">
              {p.images.map((img, i) => (
                <button key={img} className={i === imageIndex ? 'is-active' : ''} onClick={() => setImageIndex(i)} aria-label={`Image ${i + 1}`}>
                  <img src={assetUrl(img)} alt="" />
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="product__info">
          <h1>{p.name}</h1>
          <div className="product__badges">
            <span className="tag tag--big">🎮 {p.platform}</span>
            <span className="tag tag--big tag--region">🌍 {titleCase(p.region)}</span>
            <span className="tag tag--big">⭐ {p.rating.toFixed(1)}</span>
          </div>
          <dl className="specs">
            <dt>Category</dt><dd>{p.category.name}</dd>
            {p.developer && <><dt>Developer</dt><dd>{p.developer}</dd></>}
            {p.publisher && <><dt>Publisher</dt><dd>{p.publisher}</dd></>}
            {p.release_date && <><dt>Release date</dt><dd>{formatDate(p.release_date)}</dd></>}
            <dt>Region</dt><dd>{p.region === 'GLOBAL' ? 'Works worldwide' : `Can be activated in ${titleCase(p.region)}`}</dd>
          </dl>

          <div className="buy-box">
            {best ? (
              <>
                <div className="buy-box__price">
                  {p.discount > 0 && <span className="discount-badge">-{p.discount}%</span>}
                  <s className="muted">{formatPrice(p.original_price)}</s>
                  <strong>{formatPrice(best.price)}</strong>
                </div>
                <p className="muted small">Best price, from <b>{best.seller.name}</b> · {offers.filter((o) => o.in_stock).length} seller(s) in stock</p>
                <AddToCart offer={best} productName={p.name} big />
              </>
            ) : (
              <p><strong>Sold out</strong>: no seller has keys right now.</p>
            )}
          </div>
        </div>
      </div>

      <section className="section">
        <div className="section__head"><h2>Offers from sellers ({offers.length})</h2></div>
        <div className="offers">
          {offers.map((o, i) => (
            <div key={o.id} className={`offer ${o.in_stock ? '' : 'offer--out'}`}>
              <div className="offer__seller">
                <span className="avatar">{o.seller.name[0]}</span>
                <div>
                  <strong>{o.seller.name}</strong> {o.seller.is_verified && <span className="verified" title="Verified seller">✔ Verified</span>}
                  <div className="muted small">
                    {o.seller.rating.toFixed(1)}% positive · {o.seller.sales_count.toLocaleString()} sales{o.seller.country && ` · ${o.seller.country}`}
                  </div>
                </div>
              </div>
              <div className="offer__stock small">
                {o.in_stock ? (o.stock <= 3 ? <span className="warn">Only {o.stock} left</span> : <span className="ok">In stock</span>) : 'Out of stock'}
              </div>
              <div className="offer__price">
                {i === 0 && o.in_stock && <span className="pill pill--small">Best price</span>}
                <strong>{formatPrice(o.price)}</strong>
              </div>
              {o.in_stock ? <AddToCart offer={o} productName={p.name} /> : <button className="btn" disabled>Unavailable</button>}
            </div>
          ))}
        </div>
      </section>

      <section className="section">
        <div className="section__head"><h2>Description</h2></div>
        <div className="description">
          {p.description.split('\n\n').map((para, i) => <p key={i}>{para}</p>)}
        </div>
      </section>

      {p.related.length > 0 && (
        <section className="section">
          <div className="section__head"><h2>You may also like</h2></div>
          <div className="row-scroll">
            {p.related.map((r) => <ProductCard key={r.id} product={r} />)}
          </div>
        </section>
      )}
    </div>
  )
}

function AddToCart({ offer, productName, big }: { offer: Offer; productName: string; big?: boolean }) {
  const { add } = useCart()
  const toast = useToast()
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)

  const onClick = async (goToCart: boolean) => {
    setBusy(true)
    try {
      await add(offer.id, 1)
      toast(`${productName} (${offer.seller.name}) added to cart`)
      if (goToCart) navigate('/cart')
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Could not add to cart', 'error')
    } finally {
      setBusy(false)
    }
  }

  if (big) {
    return (
      <div className="buy-box__actions">
        <button className="btn btn--accent btn--lg" disabled={busy} onClick={() => onClick(true)}>Buy now</button>
        <button className="btn btn--ghost btn--lg" disabled={busy} onClick={() => onClick(false)}>Add to cart</button>
      </div>
    )
  }
  return <button className="btn btn--accent" disabled={busy} onClick={() => onClick(false)}>Add to cart</button>
}
