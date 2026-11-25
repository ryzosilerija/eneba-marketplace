import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError } from '../api/client'
import type { OrderDetail } from '../api/types'
import { ErrorBox, Spinner } from '../components/StateViews'
import { useCart } from '../context/CartContext'
import { useApi } from '../hooks/useApi'
import { formatPrice } from '../utils/format'
import { Steps } from './CheckoutPage'

const TEST_CARDS = [
  { number: '4242 4242 4242 4242', result: 'Payment succeeds' },
  { number: '4000 0000 0000 0002', result: 'Card declined' },
  { number: '4000 0000 0000 9995', result: 'Insufficient funds' },
]

/** Groups digits in fours as you type: "4242424242" → "4242 4242 42". */
const formatCard = (v: string) => v.replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ')
const formatExpiry = (v: string) => {
  const d = v.replace(/\D/g, '').slice(0, 4)
  return d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d
}

/**
 * Step 2: fake card payment. POST /api/orders/{id}/pay
 *   200 → paid, keys assigned → go to the order page (step 3)
 *   402 → declined → show the reason and let the user try again
 *   422 → invalid card fields → show them next to the inputs
 */
export default function PaymentPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const { reload } = useCart()
  const order = useApi((signal) => api<{ data: OrderDetail }>(`/api/orders/${id}`, { signal }).then((r) => r.data), [id])

  const [form, setForm] = useState({ card_number: '', card_name: '', expiry: '', cvc: '' })
  const [error, setError] = useState<ApiError | null>(null)
  const [declined, setDeclined] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  if (order.loading) return <Spinner />
  if (order.error) return <div className="container"><ErrorBox error={order.error} /><Link to="/account">Go to my orders</Link></div>
  if (!order.data) return null
  if (order.data.status === 'paid') return <Navigate to={`/orders/${id}`} replace />

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setDeclined(null)
    try {
      await api(`/api/orders/${id}/pay`, { method: 'POST', body: form })
      await reload() // purchased lines were removed from the cart on the server
      navigate(`/orders/${id}?success=1`)
    } catch (err) {
      const e = err instanceof ApiError ? err : new ApiError(0, 'Payment failed.')
      if (e.status === 402) setDeclined(e.message)
      else setError(e)
      setBusy(false)
    }
  }

  const set = (k: keyof typeof form, v: string) => setForm((f) => ({ ...f, [k]: v }))

  return (
    <div className="container checkout">
      <Steps current={2} />
      <div className="cart__layout">
        <form className="panel pay-form" onSubmit={submit} noValidate>
          <h2>Payment</h2>
          <p className="muted small">🔒 This is a simulated payment. No real card is charged. Use one of the test cards below.</p>

          {declined && (
            <div className="pay-result pay-result--failed" role="alert">
              <strong>Payment failed</strong>
              <span>{declined}</span>
              <span className="small">No money was taken and no keys were assigned. Check the details or try a different card.</span>
            </div>
          )}
          {error && !Object.keys(error.errors).length && <ErrorBox error={error} />}

          <label className="field">
            <span>Card number</span>
            <input className="input" inputMode="numeric" autoComplete="cc-number" placeholder="4242 4242 4242 4242"
              value={form.card_number} onChange={(e) => set('card_number', formatCard(e.target.value))} />
            {error?.field('card_number') && <small className="field__error">{error.field('card_number')}</small>}
          </label>
          <label className="field">
            <span>Name on card</span>
            <input className="input" autoComplete="cc-name" placeholder="Jane Doe" value={form.card_name} onChange={(e) => set('card_name', e.target.value)} />
            {error?.field('card_name') && <small className="field__error">{error.field('card_name')}</small>}
          </label>
          <div className="field-row">
            <label className="field">
              <span>Expiry (MM/YY)</span>
              <input className="input" inputMode="numeric" autoComplete="cc-exp" placeholder="12/30" value={form.expiry} onChange={(e) => set('expiry', formatExpiry(e.target.value))} />
              {error?.field('expiry') && <small className="field__error">{error.field('expiry')}</small>}
            </label>
            <label className="field">
              <span>CVC</span>
              <input className="input" inputMode="numeric" autoComplete="cc-csc" placeholder="123" maxLength={4} value={form.cvc} onChange={(e) => set('cvc', e.target.value.replace(/\D/g, ''))} />
              {error?.field('cvc') && <small className="field__error">{error.field('cvc')}</small>}
            </label>
          </div>

          <button className="btn btn--accent btn--lg btn--block" disabled={busy}>
            {busy ? 'Processing payment…' : `Pay ${formatPrice(order.data.total)}`}
          </button>

          <div className="test-cards">
            <strong className="small">Test cards (any future expiry, any CVC):</strong>
            {TEST_CARDS.map((c) => (
              <button type="button" key={c.number} className="test-card" onClick={() => setForm({ card_number: c.number, card_name: 'Test User', expiry: '12/30', cvc: '123' })}>
                <code>{c.number}</code><span className="muted">{c.result}</span>
              </button>
            ))}
          </div>
        </form>

        <aside className="summary">
          <h2>Order #{order.data.id}</h2>
          {order.data.items.map((i) => (
            <div key={i.id} className="summary__row small"><span>{i.product_name} ×{i.quantity}</span><span>{formatPrice(i.line_total)}</span></div>
          ))}
          <div className="summary__row summary__total"><span>Total</span><strong>{formatPrice(order.data.total)}</strong></div>
        </aside>
      </div>
    </div>
  )
}
