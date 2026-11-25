import { useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { api, assetUrl } from '../api/client'
import type { OrderDetail, OrderStatus, OrderSummary } from '../api/types'
import { Empty, ErrorBox, Spinner } from '../components/StateViews'
import { useAuth } from '../context/AuthContext'
import { useApi } from '../hooks/useApi'
import { formatDate, formatPrice } from '../utils/format'
import { Steps } from './CheckoutPage'

const STATUS_LABEL: Record<OrderStatus, string> = { paid: 'Paid', pending: 'Awaiting payment', failed: 'Payment failed' }

export function StatusBadge({ status }: { status: OrderStatus }) {
  return <span className={`status status--${status}`}>{STATUS_LABEL[status]}</span>
}

/** GET /api/orders returns only the current user's orders (the query is filtered by user_id on the server). */
export function AccountPage() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const orders = useApi((signal) => api<{ data: OrderSummary[] }>('/api/orders', { signal }).then((r) => r.data), [])

  return (
    <div className="container account">
      <div className="account__head panel">
        <span className="avatar avatar--lg">{user?.name[0]}</span>
        <div>
          <h1>{user?.name}</h1>
          <p className="muted">{user?.email} · member since {formatDate(user?.created_at)}</p>
        </div>
        <button className="btn btn--ghost" onClick={async () => { await logout(); navigate('/') }}>Log out</button>
      </div>

      <h2>My orders</h2>
      {orders.loading && <Spinner />}
      {orders.error && <ErrorBox error={orders.error} onRetry={orders.retry} />}
      {orders.data && orders.data.length === 0 && (
        <Empty title="No orders yet"><Link to="/store" className="btn btn--accent">Start shopping</Link></Empty>
      )}
      {orders.data && orders.data.length > 0 && (
        <div className="orders">
          {orders.data.map((o) => (
            <Link key={o.id} to={o.status === 'paid' ? `/orders/${o.id}` : `/checkout/pay/${o.id}`} className="order-row">
              <div>
                <strong>Order #{o.id}</strong>
                <div className="muted small">{formatDate(o.created_at)} · {o.item_count} item(s)</div>
              </div>
              <div className="order-row__summary small">{o.summary}</div>
              <StatusBadge status={o.status} />
              <strong>{formatPrice(o.total)}</strong>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}

/**
 * Order detail with the digital keys. The API returns 404 for orders that belong to someone else,
 * and keys are only included once the order is paid.
 */
export function OrderPage() {
  const { id = '' } = useParams()
  const [params] = useSearchParams()
  const order = useApi((signal) => api<{ data: OrderDetail }>(`/api/orders/${id}`, { signal }).then((r) => r.data), [id])

  if (order.loading) return <Spinner />
  if (order.error?.status === 404) {
    return <div className="container"><Empty title="Order not found"><Link to="/account" className="btn btn--accent">My orders</Link></Empty></div>
  }
  if (order.error || !order.data) return <div className="container"><ErrorBox error={order.error ?? new Error('Not found')} onRetry={order.retry} /></div>

  const o = order.data
  const justPaid = params.get('success') === '1' && o.status === 'paid'

  return (
    <div className="container checkout">
      {justPaid && (
        <>
          <Steps current={3} />
          <div className="pay-result pay-result--success" role="status">
            <strong>🎉 Payment successful!</strong>
            <span>Your keys are ready below. Payment reference: <code>{o.payment_reference}</code></span>
          </div>
        </>
      )}
      <div className="order-head">
        <div>
          <Link to="/account" className="muted small">← My orders</Link>
          <h1>Order #{o.id}</h1>
          <p className="muted">Placed {formatDate(o.created_at)}{o.paid_at && ` · Paid ${formatDate(o.paid_at)}`}</p>
        </div>
        <StatusBadge status={o.status} />
      </div>
      {o.status !== 'paid' && (
        <div className="pay-result pay-result--failed">
          <strong>{o.status === 'failed' ? 'The last payment attempt failed' : 'This order has not been paid yet'}</strong>
          {o.failure_reason && <span>{o.failure_reason}</span>}
          <Link to={`/checkout/pay/${o.id}`} className="btn btn--accent">Pay now</Link>
        </div>
      )}

      <div className="panel">
        {o.items.map((i) => (
          <div key={i.id} className="order-item">
            <img src={assetUrl(i.cover)} alt="" />
            <div className="order-item__info">
              {i.product_slug ? <Link to={`/product/${i.product_slug}`}><strong>{i.product_name}</strong></Link> : <strong>{i.product_name}</strong>}
              <div className="muted small">{i.platform} · {i.region} · Seller: {i.seller_name} · {i.quantity} × {formatPrice(i.unit_price)}</div>
              {i.keys.length > 0 && (
                <div className="keys">
                  {i.keys.map((k) => <KeyCode key={k} code={k} />)}
                </div>
              )}
            </div>
            <strong>{formatPrice(i.line_total)}</strong>
          </div>
        ))}
        <div className="summary__row summary__total"><span>Total</span><strong>{formatPrice(o.total)}</strong></div>
      </div>
      {o.status === 'paid' && <p className="muted small">These are fictional test keys for this demo. They won't activate anywhere.</p>}
    </div>
  )
}

function KeyCode({ code }: { code: string }) {
  const [revealed, setRevealed] = useState(false)
  const [copied, setCopied] = useState(false)
  return (
    <div className="key">
      <code>{revealed ? code : code.replace(/[A-Z0-9]/g, '•')}</code>
      <button className="link-btn" onClick={() => setRevealed((r) => !r)}>{revealed ? 'Hide' : 'Reveal'}</button>
      <button
        className="link-btn"
        onClick={() => {
          navigator.clipboard?.writeText(code)
          setCopied(true)
          setTimeout(() => setCopied(false), 1500)
        }}
      >
        {copied ? 'Copied!' : 'Copy'}
      </button>
    </div>
  )
}
