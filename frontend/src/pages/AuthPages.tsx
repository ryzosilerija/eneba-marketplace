import { useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { ApiError } from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'

/** Where to go after logging in: back to the page that required auth, or the account page. */
function useRedirectTarget() {
  const location = useLocation()
  return (location.state as { from?: string } | null)?.from ?? '/account'
}

export function LoginPage() {
  const { login, user } = useAuth()
  const navigate = useNavigate()
  const toast = useToast()
  const target = useRedirectTarget()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to={target} replace />

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await login(email, password)
      toast('Welcome back!')
      navigate(target, { replace: true })
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Login failed.'))
      setBusy(false)
    }
  }

  return (
    <div className="container auth">
      <form className="panel auth__card" onSubmit={submit} noValidate>
        <h1>Log in</h1>
        {error && !Object.keys(error.errors).length && <div className="error-box" role="alert">{error.message}</div>}
        <label className="field">
          <span>Email</span>
          <input className="input" type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          {error?.field('email') && <small className="field__error">{error.field('email')}</small>}
        </label>
        <label className="field">
          <span>Password</span>
          <input className="input" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          {error?.field('password') && <small className="field__error">{error.field('password')}</small>}
        </label>
        <button className="btn btn--accent btn--lg btn--block" disabled={busy}>{busy ? 'Logging in…' : 'Log in'}</button>
        <p className="muted small center">No account? <Link to="/register" state={{ from: target }}>Register</Link></p>
        <div className="demo-hint small">
          Demo account: <code>demo@keyvault.test</code> / <code>password123</code>
          <button type="button" className="link-btn" onClick={() => { setEmail('demo@keyvault.test'); setPassword('password123') }}>Fill in</button>
        </div>
      </form>
    </div>
  )
}

export function RegisterPage() {
  const { register, user } = useAuth()
  const navigate = useNavigate()
  const toast = useToast()
  const target = useRedirectTarget()
  const [form, setForm] = useState({ name: '', email: '', password: '' })
  const [error, setError] = useState<ApiError | null>(null)
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to={target} replace />

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await register(form.name, form.email, form.password)
      toast('Account created. Welcome to KeyVault!')
      navigate(target, { replace: true })
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Registration failed.'))
      setBusy(false)
    }
  }

  const field = (name: keyof typeof form, label: string, type: string, autoComplete: string, hint?: string) => (
    <label className="field">
      <span>{label}</span>
      <input className="input" type={type} autoComplete={autoComplete} value={form[name]} onChange={(e) => setForm((f) => ({ ...f, [name]: e.target.value }))} required />
      {error?.field(name) ? <small className="field__error">{error.field(name)}</small> : hint && <small className="muted">{hint}</small>}
    </label>
  )

  return (
    <div className="container auth">
      <form className="panel auth__card" onSubmit={submit} noValidate>
        <h1>Create account</h1>
        {error && !Object.keys(error.errors).length && <div className="error-box" role="alert">{error.message}</div>}
        {field('name', 'Name', 'text', 'name')}
        {field('email', 'Email', 'email', 'email')}
        {field('password', 'Password', 'password', 'new-password', 'At least 8 characters')}
        <button className="btn btn--accent btn--lg btn--block" disabled={busy}>{busy ? 'Creating account…' : 'Register'}</button>
        <p className="muted small center">Already have an account? <Link to="/login" state={{ from: target }}>Log in</Link></p>
      </form>
    </div>
  )
}
