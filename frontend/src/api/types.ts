// Shapes of the JSON returned by the PHP API. All money values are integer cents.

export interface Category {
  slug: string
  name: string
}

/** A product as shown on a card (listing, homepage sections). */
export interface ProductCard {
  id: number
  slug: string
  name: string
  platform: string
  region: string
  category: Category
  cover: string | null
  rating: number
  release_date: string | null
  original_price: number
  min_price: number | null // cheapest in-stock offer; null = sold out everywhere
  discount: number // percent vs original_price
  offer_count: number
  in_stock: boolean
}

/** One seller's offer for a product. The cart stores offers, not products. */
export interface Offer {
  id: number
  price: number
  stock: number
  in_stock: boolean
  seller: {
    id: number
    name: string
    slug: string
    country: string | null
    rating: number
    sales_count: number
    is_verified: boolean
  }
}

export interface ProductDetail {
  id: number
  slug: string
  name: string
  description: string
  platform: string
  region: string
  developer: string | null
  publisher: string | null
  release_date: string | null
  rating: number
  sales_count: number
  original_price: number
  min_price: number | null
  discount: number
  category: Category
  images: string[]
  offers: Offer[]
  related: ProductCard[]
}

export interface Paginated<T> {
  data: T[]
  meta: { total: number; page: number; per_page: number; last_page: number; sort: string }
}

export interface Facet {
  value: string
  label?: string
  count: number
}

export interface Filters {
  platforms: Facet[]
  regions: Facet[]
  categories: Facet[]
  price: { min: number; max: number }
}

export interface Suggestion {
  slug: string
  name: string
  platform: string
  region: string
  cover: string | null
  min_price: number | null
}

export interface User {
  id: number
  name: string
  email: string
  created_at: string
}

export interface CartItem {
  id: number
  quantity: number
  unit_price: number
  line_total: number
  stock: number
  available: boolean
  offer_id: number
  seller: { name: string; slug: string }
  product: { id: number; slug: string; name: string; platform: string; region: string; cover: string | null }
}

export interface Cart {
  cart_token: string | null
  items: CartItem[]
  item_count: number
  total: number
}

export type OrderStatus = 'pending' | 'paid' | 'failed'

export interface OrderSummary {
  id: number
  status: OrderStatus
  total: number
  created_at: string
  paid_at: string | null
  failure_reason: string | null
  payment_reference: string | null
  item_count: number
  summary: string
}

export interface OrderItem {
  id: number
  product_name: string
  product_slug: string | null
  cover: string | null
  seller_name: string
  platform: string
  region: string
  unit_price: number
  quantity: number
  line_total: number
  keys: string[] // only filled in once the order is paid
}

export interface OrderDetail extends Omit<OrderSummary, 'item_count' | 'summary'> {
  items: OrderItem[]
}
