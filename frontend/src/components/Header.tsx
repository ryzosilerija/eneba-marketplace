import { useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import SearchBox from './SearchBox'

const CATEGORY_LINKS = [
  { label: 'Steam', to: '/store?platform=Steam' },
  { label: 'PlayStation', to: '/store?platform=PlayStation' },
  { label: 'Xbox', to: '/store?platform=Xbox' },
  { label: 'Nintendo', to: '/store?platform=Nintendo' },
  { label: 'Gift Cards', to: '/store?category=gift-cards' },
  { label: 'RPG', to: '/store?category=rpg' },
  { label: 'Shooters', to: '/store?category=shooter' },
  { label: 'Indie', to: '/store?category=indie' },
  { label: 'Best deals', to: '/store?sort=discount' },
  { label: 'New releases', to: '/store?sort=newest' },
]

export function Logo() {
  return (
    <Link to="/" className="logo" aria-label="KeyVault home">
      <span className="logo__mark">K</span>
      <span className="logo__text">
        Key<b>Vault</b>
      </span>
    </Link>
  )
}

export default function Header() {
  const { user, logout } = useAuth()
  const { cart } = useCart()
  const navigate = useNavigate()
  const [searchOpen, setSearchOpen] = useState(false)

  return (
    <header className="header">
      <div className="container header__main">
        <Logo />
        <div className={`header__search ${searchOpen ? 'is-open' : ''}`}>
          <SearchBox onDone={() => setSearchOpen(false)} />
        </div>
        <nav className="header__actions">
          <button className="icon-btn header__search-toggle" onClick={() => setSearchOpen((o) => !o)} aria-label="Search">
            <svg viewBox="0 0 24 24"><path d="M10.5 3a7.5 7.5 0 0 1 5.92 12.1l4.24 4.24-1.42 1.42-4.24-4.24A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z" /></svg>
          </button>
          {user ? (
            <div className="account-menu">
              <NavLink to="/account" className="icon-btn" aria-label="My account">
                <svg viewBox="0 0 24 24"><path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-4.4 0-8 2.2-8 5v2h16v-2c0-2.8-3.6-5-8-5Z" /></svg>
                <span className="hide-sm">{user.name.split(' ')[0]}</span>
              </NavLink>
              <button
                className="link-btn hide-sm"
                onClick={async () => {
                  await logout()
                  navigate('/')
                }}
              >
                Log out
              </button>
            </div>
          ) : (
            <>
              <NavLink to="/login" className="link-btn">Log in</NavLink>
              <NavLink to="/register" className="btn btn--small btn--accent hide-sm">Register</NavLink>
            </>
          )}
          <NavLink to="/cart" className="icon-btn cart-btn" aria-label={`Cart, ${cart.item_count} items`}>
            <svg viewBox="0 0 24 24"><path d="M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM3 2h2.2l.9 2H21l-3.4 8.5a2 2 0 0 1-1.9 1.5H8.1l-.9 2H19v2H4.6l2.2-4.4L4 4H3V2Z" /></svg>
            {cart.item_count > 0 && <span className="badge">{cart.item_count}</span>}
          </NavLink>
        </nav>
      </div>
      <nav className="catbar" aria-label="Categories">
        <div className="container catbar__inner">
          <NavLink to="/store" end className="catbar__all">☰ All products</NavLink>
          {CATEGORY_LINKS.map((l) => (
            <Link key={l.label} to={l.to}>{l.label}</Link>
          ))}
        </div>
      </nav>
    </header>
  )
}
