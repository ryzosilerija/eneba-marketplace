/**
 * Thin wrapper around fetch() for talking to the PHP REST API.
 *
 * - Adds the JSON headers, the login token (Authorization: Bearer …) and the guest cart token (X-Cart-Token).
 * - Turns non-2xx responses into an ApiError carrying the status code and the API's
 *   {"message": …, "errors": {field: [..]}} body, so pages can show proper error messages.
 */

// Empty in dev and in the single-container deployment (same origin). Set VITE_API_URL if the API lives elsewhere.
export const API_URL: string = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

const TOKEN_KEY = 'kv_token'
const CART_KEY = 'kv_cart_token'

export const storage = {
  getToken: () => localStorage.getItem(TOKEN_KEY),
  setToken: (t: string | null) => (t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY)),
  getCartToken: () => localStorage.getItem(CART_KEY),
  setCartToken: (t: string | null) => (t ? localStorage.setItem(CART_KEY, t) : localStorage.removeItem(CART_KEY)),
}

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>
  data: unknown

  constructor(status: number, message: string, errors: Record<string, string[]> = {}, data: unknown = null) {
    super(message)
    this.status = status
    this.errors = errors
    this.data = data
  }

  /** First validation message for a form field, if any. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0]
  }
}

type Query = Record<string, string | number | boolean | undefined | null>

export async function api<T>(
  path: string,
  options: { method?: string; body?: unknown; query?: Query; signal?: AbortSignal } = {},
): Promise<T> {
  const url = new URL(API_URL + path, window.location.origin)
  for (const [k, v] of Object.entries(options.query ?? {})) {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, String(v))
  }

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (options.body !== undefined) headers['Content-Type'] = 'application/json'
  const token = storage.getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  const cartToken = storage.getCartToken()
  if (cartToken) headers['X-Cart-Token'] = cartToken

  let res: Response
  try {
    res = await fetch(url, {
      method: options.method ?? 'GET',
      headers,
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
      signal: options.signal,
    })
  } catch (e) {
    if ((e as Error).name === 'AbortError') throw e
    throw new ApiError(0, 'Could not reach the server. Check your connection and try again.')
  }

  if (res.status === 204) return undefined as T
  const data = await res.json().catch(() => null)
  if (!res.ok) {
    throw new ApiError(res.status, data?.message ?? `Request failed (${res.status}).`, data?.errors ?? {}, data)
  }
  return data as T
}

/** Image paths from the API are relative (/images/...). Prefix them when the API is on another host. */
export function assetUrl(path: string | null | undefined): string {
  if (!path) return ''
  return path.startsWith('http') ? path : API_URL + path
}
