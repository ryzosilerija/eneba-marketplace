import { Link } from 'react-router-dom'
import HeroBanner from '../components/HeroBanner'
import ProductSection from '../components/ProductSection'
import { useAuth } from '../context/AuthContext'

const PLATFORMS = [
  { name: 'Steam', color: '#1b2838' },
  { name: 'PlayStation', color: '#0050d8' },
  { name: 'Xbox', color: '#107c10' },
  { name: 'Nintendo', color: '#e60012' },
  { name: 'EA App', color: '#ff4747' },
  { name: 'GOG', color: '#86328a' },
]

export default function HomePage() {
  const { user } = useAuth()
  return (
    <div className="container">
      <HeroBanner />

      <div className="platform-strip">
        {PLATFORMS.map((p) => (
          <Link key={p.name} to={`/store?platform=${encodeURIComponent(p.name)}`} style={{ '--platform': p.color } as React.CSSProperties}>
            {p.name}
          </Link>
        ))}
      </div>

      {/* Each section loads independently from its own API endpoint. */}
      <ProductSection section="popular" title="Popular games" subtitle="Best sellers this week" showAllLink="/store?sort=popular" />
      <ProductSection section="deals" title="Best deals" subtitle="Biggest discounts vs. list price" showAllLink="/store?sort=discount" />

      <Link to="/store?category=gift-cards" className="banner-wide">
        <div>
          <span className="pill">Instant delivery</span>
          <h2>Top up your wallet</h2>
          <p>Steam, PlayStation, Xbox and Nintendo gift cards from trusted sellers.</p>
        </div>
        <span className="btn btn--accent">Shop gift cards</span>
      </Link>

      <ProductSection section="new" title="New releases" subtitle="Recently released games" showAllLink="/store?sort=newest" />
      <ProductSection
        section="recommended"
        title={user ? `Recommended for you, ${user.name.split(' ')[0]}` : 'Recommended'}
        subtitle={user ? 'Based on what you bought' : 'Top-rated picks from our sellers'}
        showAllLink="/store?sort=rating"
      />

      <div className="trust-row">
        <div><strong>⚡ Instant delivery</strong><span className="muted">Keys appear in your account right after payment</span></div>
        <div><strong>🛡 Verified sellers</strong><span className="muted">Compare ratings before you buy</span></div>
        <div><strong>💸 Best prices</strong><span className="muted">Several sellers compete on every product</span></div>
      </div>
    </div>
  )
}
