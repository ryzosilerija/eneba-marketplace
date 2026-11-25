import { Link } from 'react-router-dom'
import { Logo } from './Header'

export default function Footer() {
  return (
    <footer className="footer">
      <div className="container footer__grid">
        <div>
          <Logo />
          <p className="muted footer__about">
            A student demo marketplace for digital game keys. Every key here is fictional and every payment is simulated.
          </p>
        </div>
        <div>
          <h4>Shop</h4>
          <Link to="/store">All products</Link>
          <Link to="/store?sort=discount">Best deals</Link>
          <Link to="/store?sort=newest">New releases</Link>
          <Link to="/store?category=gift-cards">Gift cards</Link>
        </div>
        <div>
          <h4>Platforms</h4>
          <Link to="/store?platform=Steam">Steam</Link>
          <Link to="/store?platform=PlayStation">PlayStation</Link>
          <Link to="/store?platform=Xbox">Xbox</Link>
          <Link to="/store?platform=Nintendo">Nintendo</Link>
        </div>
        <div>
          <h4>Account</h4>
          <Link to="/account">My orders</Link>
          <Link to="/cart">Cart</Link>
          <Link to="/login">Log in</Link>
          <Link to="/register">Register</Link>
        </div>
      </div>
      <div className="container footer__bottom">
        <span className="pay-logos" aria-label="Accepted payment methods (simulated)">
          <span>VISA</span><span>Mastercard</span><span>PayPal</span><span>Apple Pay</span>
        </span>
        <span className="muted">© {new Date().getFullYear()} KeyVault, a demo project. Not affiliated with Eneba.</span>
      </div>
    </footer>
  )
}
