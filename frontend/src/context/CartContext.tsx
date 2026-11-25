import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'
import { api, storage } from '../api/client'
import type { Cart } from '../api/types'
import { useAuth } from './AuthContext'

interface CartContextValue {
  cart: Cart
  loading: boolean
  add: (offerId: number, quantity?: number) => Promise<void>
  update: (itemId: number, quantity: number) => Promise<void>
  remove: (itemId: number) => Promise<void>
  reload: () => Promise<void>
}

const EMPTY: Cart = { cart_token: null, items: [], item_count: 0, total: 0 }
const CartContext = createContext<CartContextValue | null>(null)

/**
 * The cart lives on the server (tables carts + cart_items), so it survives refreshes.
 * Guests are identified by a cart token the API hands out on first "add"; we keep it in localStorage.
 * Every cart endpoint returns the whole updated cart, so we simply replace our state with it.
 */
export function CartProvider({ children }: { children: ReactNode }) {
  const { user, ready } = useAuth()
  const [cart, setCart] = useState<Cart>(EMPTY)
  const [loading, setLoading] = useState(true)

  const apply = (c: Cart) => {
    if (c.cart_token) storage.setCartToken(c.cart_token) // guest cart
    setCart(c)
  }

  const reload = useCallback(async () => {
    try {
      apply(await api<Cart>('/api/cart'))
    } catch {
      setCart(EMPTY)
    } finally {
      setLoading(false)
    }
  }, [])

  // Reload when auth state is known or changes (login merges carts, logout switches to an empty guest cart).
  useEffect(() => {
    if (ready) reload()
  }, [ready, user?.id, reload])

  const add = useCallback(async (offerId: number, quantity = 1) => {
    apply(await api<Cart>('/api/cart/items', { method: 'POST', body: { offer_id: offerId, quantity } }))
  }, [])

  const update = useCallback(async (itemId: number, quantity: number) => {
    apply(await api<Cart>(`/api/cart/items/${itemId}`, { method: 'PATCH', body: { quantity } }))
  }, [])

  const remove = useCallback(async (itemId: number) => {
    apply(await api<Cart>(`/api/cart/items/${itemId}`, { method: 'DELETE' }))
  }, [])

  return (
    <CartContext.Provider value={{ cart, loading, add, update, remove, reload }}>{children}</CartContext.Provider>
  )
}

export function useCart() {
  const ctx = useContext(CartContext)
  if (!ctx) throw new Error('useCart must be used inside <CartProvider>')
  return ctx
}
