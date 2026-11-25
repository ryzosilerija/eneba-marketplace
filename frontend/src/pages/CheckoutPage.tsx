import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, ApiError, assetUrl } from '../api/client'
import type { OrderDetail } from '../api/types'
import { Empty, ErrorBox, Spinner } from '../components/StateViews'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { formatPrice } from '../utils/format'

/**
 * Step 1 of checkout: review the cart and place the order.
 * POST /api/checkout creates a PENDING order with the prices frozen, then we go to the payment step.
 */
export default function CheckoutPage() {
  const { cart, loading } = useCart()
  const { user } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<ApiError | null>(null)
  const [busy, setBusy] = useState(false)

  if (loading) return <Spinner />
  if (cart.items.length === 0) {
    return <div className="container"><Empty title="Nothing to check out"><Link to="/store" className="btn btn--accent">Browse products</Link></Empty></div>
  }

  const placeOrder = async () => {
    setBusy(true)
    setError(null)
    try {
      const r = await api<{ data: OrderDetail }>('/api/checkout', { method: 'POST' })
      navigate(`/checkout/pay/${r.data.id}`)
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, 'Could not place the order.'))
      setBusy(false)
    }
  }

  return (
    <div className="container checkout">
      <Steps current={1} />
      <div className="cart__layout">
        <div className="panel">
          <h2>Review your order</h2>
          {cart.items.map((i) => (
            <div key={i.id} className="review-line">
              <img src={assetUrl(i.product.cover)} alt="" />
              <div>
                <strong>{i.product.name}</strong>
                <div className="muted small">{i.product.platform} · {i.product.region} · Seller: {i.seller.name}</div>
              </div>
              <span className="muted">×{i.quantity}</span>
              <strong>{formatPrice(i.line_total)}</strong>
            </div>
          ))}
          <h3 className="mt">Delivery</h3>
          <p className="muted">Digital keys are delivered instantly to your account ({user?.email}) after payment.</p>
        </div>
        <aside className="summary">
          <h2>Total</h2>
          <div className="summary__row summary__total"><span>To pay</span><strong>{formatPrice(cart.total)}</strong></div>
          {error && <ErrorBox error={error} />}
          <button className="btn btn--accent btn--lg btn--block" onClick={placeOrder} disabled={busy}>
            {busy ? 'Placing order…' : 'Continue to payment'}
          </button>
          <Link to="/cart" className="muted small center">← Back to cart</Link>
        </aside>
      </div>
    </div>
  )
}

export function Steps({ current }: { current: 1 | 2 | 3 }) {
  const steps = ['Review', 'Payment', 'Keys']
  return (
    <ol className="steps">
      {steps.map((s, i) => (
        <li key={s} className={i + 1 < current ? 'is-done' : i + 1 === current ? 'is-current' : ''}>
          <span>{i + 1 < current ? '✓' : i + 1}</span>{s}
        </li>
      ))}
    </ol>
  )
}
