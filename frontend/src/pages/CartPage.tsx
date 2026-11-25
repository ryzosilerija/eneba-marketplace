import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ApiError, assetUrl } from '../api/client'
import type { CartItem } from '../api/types'
import { Empty, Spinner } from '../components/StateViews'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { useToast } from '../context/ToastContext'
import { formatPrice, titleCase } from '../utils/format'

const MAX_QTY = 10 // same limit as CartService::MAX_QUANTITY on the server

export default function CartPage() {
  const { cart, loading } = useCart()
  const { user } = useAuth()
  const navigate = useNavigate()

  if (loading) return <Spinner />
  if (cart.items.length === 0) {
    return (
      <div className="container">
        <Empty title="Your cart is empty">
          <p className="muted">Find something you like in the store.</p>
          <Link to="/store" className="btn btn--accent">Browse products</Link>
        </Empty>
      </div>
    )
  }

  const blocked = cart.items.some((i) => !i.available)

  return (
    <div className="container cart">
      <h1>Cart <span className="muted">({cart.item_count})</span></h1>
      <div className="cart__layout">
        <div className="cart__items">
          {cart.items.map((item) => <CartLine key={item.id} item={item} />)}
        </div>
        <aside className="summary">
          <h2>Order summary</h2>
          <div className="summary__row"><span>Items ({cart.item_count})</span><span>{formatPrice(cart.total)}</span></div>
          <div className="summary__row"><span>Service fee</span><span>{formatPrice(0)}</span></div>
          <div className="summary__row summary__total"><span>Total</span><strong>{formatPrice(cart.total)}</strong></div>
          {blocked && <p className="warn small">Some items don't have enough stock. Lower the quantity or remove them.</p>}
          <button className="btn btn--accent btn--lg btn--block" disabled={blocked} onClick={() => navigate(user ? '/checkout' : '/login', { state: { from: '/checkout' } })}>
            {user ? 'Proceed to checkout' : 'Log in to checkout'}
          </button>
          {!user && <p className="muted small">Your cart is saved and will be kept when you log in.</p>}
        </aside>
      </div>
    </div>
  )
}

function CartLine({ item }: { item: CartItem }) {
  const { update, remove } = useCart()
  const toast = useToast()
  const [busy, setBusy] = useState(false)

  const run = async (fn: () => Promise<void>) => {
    setBusy(true)
    try {
      await fn()
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Something went wrong', 'error')
    } finally {
      setBusy(false)
    }
  }

  const maxQty = Math.max(1, Math.min(MAX_QTY, item.stock))

  return (
    <div className={`cart-line ${busy ? 'is-busy' : ''}`}>
      <Link to={`/product/${item.product.slug}`} className="cart-line__img">
        <img src={assetUrl(item.product.cover)} alt="" />
      </Link>
      <div className="cart-line__info">
        <Link to={`/product/${item.product.slug}`}><strong>{item.product.name}</strong></Link>
        <div className="muted small">{item.product.platform} · {titleCase(item.product.region)}</div>
        {/* The seller the user picked is part of the cart line. */}
        <div className="small">Seller: <b>{item.seller.name}</b> · {formatPrice(item.unit_price)} each</div>
        {!item.available && <div className="warn small">Only {item.stock} in stock</div>}
      </div>
      <div className="qty" aria-label="Quantity">
        <button disabled={busy || item.quantity <= 1} onClick={() => run(() => update(item.id, item.quantity - 1))} aria-label="Decrease">−</button>
        <span>{item.quantity}</span>
        <button disabled={busy || item.quantity >= maxQty} onClick={() => run(() => update(item.id, item.quantity + 1))} aria-label="Increase">+</button>
      </div>
      <strong className="cart-line__total">{formatPrice(item.line_total)}</strong>
      <button className="icon-btn cart-line__remove" disabled={busy} onClick={() => run(() => remove(item.id))} aria-label="Remove">✕</button>
    </div>
  )
}
