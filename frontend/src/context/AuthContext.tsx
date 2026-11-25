import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'
import { api, storage } from '../api/client'
import type { User } from '../api/types'

interface AuthContextValue {
  user: User | null
  ready: boolean // false until we've checked a saved token on page load
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

/**
 * Keeps the logged-in user. The API token is stored in localStorage so the session survives a refresh;
 * on load we call /api/auth/me to check that the token is still valid.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    if (!storage.getToken()) {
      setReady(true)
      return
    }
    api<{ user: User }>('/api/auth/me')
      .then((r) => setUser(r.user))
      .catch(() => storage.setToken(null)) // expired / revoked token
      .finally(() => setReady(true))
  }, [])

  // The server merges the guest cart (sent via X-Cart-Token) into the user's cart on login/register,
  // so afterwards the guest cart token is no longer needed.
  const handleAuth = (r: { user: User; token: string }) => {
    storage.setToken(r.token)
    storage.setCartToken(null)
    setUser(r.user)
  }

  const login = useCallback(async (email: string, password: string) => {
    handleAuth(await api('/api/auth/login', { method: 'POST', body: { email, password } }))
  }, [])

  const register = useCallback(async (name: string, email: string, password: string) => {
    handleAuth(await api('/api/auth/register', { method: 'POST', body: { name, email, password } }))
  }, [])

  const logout = useCallback(async () => {
    try {
      await api('/api/auth/logout', { method: 'POST' })
    } finally {
      storage.setToken(null)
      setUser(null)
    }
  }, [])

  return <AuthContext.Provider value={{ user, ready, login, register, logout }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>')
  return ctx
}
