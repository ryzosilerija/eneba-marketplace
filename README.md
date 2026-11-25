# KeyVault: digital game key marketplace

An Eneba-style marketplace where several sellers offer keys for the same game at different prices. Built with **React + TypeScript** (frontend), **plain PHP 8.3** (REST API) and **PostgreSQL**.

> All keys are fictional (`KVTEST-XXXXX-XXXXX-XXXXX`) and all payments are simulated.

## Features

| Area | What works |
|---|---|
| **Homepage** | Rotating featured banner, plus *Popular games*, *Best deals*, *New releases* and *Recommended* rows. Each row calls its own endpoint (`/api/home/{section}`) and has its own loading, error and retry state, so if one fails the others still load. *Recommended* is personalised for logged-in users with past orders. |
| **Store** | Filter by category, platform, region, price range and "in stock"; sort by 8 options; paginate. Everything runs in SQL on the server. Filters live in the URL, so links and the back button work. Filters open as a drawer on mobile. |
| **Search** | Header search with suggestions while you type, sent 300 ms after you stop typing (debounced), with outdated requests cancelled. Matches partial names (`witch` → *The Witcher 3*) and small typos (`cyberpnk` → *Cyberpunk 2077*) using PostgreSQL trigrams. Keyboard navigation (↑ ↓ Enter Esc). |
| **Product page** | Image gallery, description, platform, region, developer/publisher, release date, and a table of **seller offers** (price, stock, seller rating, verified badge) with *Add to cart* per offer. |
| **Cart** | Stored in the database: survives refreshes and works for guests (cart token) and users. Every line keeps the **selected offer (seller)**. Change quantity (max 10 and never more than stock), remove items, see the total. The guest cart is merged into your account when you log in. |
| **Checkout** | 3 steps: review, then fake card payment, then keys. Success, *declined* and *insufficient funds* outcomes. A successful payment marks the order paid and assigns one unique key per unit bought. |
| **Accounts** | Register, log in, log out, account page with order history, order detail with keys (masked until you click *Reveal*, with copy to clipboard). |
| **Security** | Users can only see their own orders and keys (other users' orders return 404). Keys are only shown for paid orders. Only a hash of each login token is stored. |

## Example accounts

| Email | Password |
|---|---|
| `demo@keyvault.test` | `password123` |
| `alice@keyvault.test` | `password123` |

### Test cards (fake payment)

| Card number | Result |
|---|---|
| `4242 4242 4242 4242` | ✅ Payment succeeds |
| `4000 0000 0000 0002` | ❌ Card declined |
| `4000 0000 0000 9995` | ❌ Insufficient funds |

Use any future expiry (e.g. `12/30`) and any 3-digit CVC. Any other number that passes the Luhn checksum also succeeds. The payment page has buttons that fill these in for you.

---

## Project structure

```
keyvault/
├── backend/                  PHP REST API (no framework)
│   ├── public/index.php      Front controller: CORS, then hands every request to App
│   ├── src/
│   │   ├── routes.php        All API routes in one place
│   │   ├── App.php           Turns a Request into a Response; exceptions become JSON errors
│   │   ├── Core/             Router, Request, Response, Validator, Database, Auth, HttpException
│   │   ├── Controllers/      Read input, validate, call a service, return JSON
│   │   ├── Services/         Business rules: Catalog, Search, Cart, Checkout, Orders, FakePaymentGateway
│   │   └── Models/           SQL queries for each table (Product, Offer, Cart, Order, DigitalKey, User)
│   ├── database/
│   │   ├── migrations/001_schema.sql
│   │   ├── migrate.php       Creates tables (and optionally seeds)
│   │   └── Seeder.php        38 products, 8 sellers, 84 offers, ~590 keys, 2 users, SVG cover images
│   └── tests/                37 API tests + a small test runner
├── frontend/                 React + TypeScript (Vite)
│   └── src/
│       ├── api/              fetch wrapper (client.ts) and API types (types.ts)
│       ├── context/          AuthContext, CartContext, ToastContext
│       ├── hooks/            useApi (loading/error/retry), useDebounce
│       ├── components/       Header, SearchBox, ProductCard, ProductSection, HeroBanner…
│       └── pages/            Home, Store, Product, Cart, Checkout, Payment, Login/Register, Account/Order
├── e2e/smoke_test.py         Browser test of the full user journey (Playwright)
├── docker/                   Apache config + start script for the production container
├── Dockerfile                Builds the React app and serves it together with the API
├── render.yaml               One-click deployment to Render (web service + PostgreSQL)
└── docs/ai-development-log.md
```

### Why plain PHP instead of Laravel?

The backend already existed and worked when this phase started (all tests passing), so it was kept rather than rewritten. It follows the same ideas as Laravel (routes file, controllers, services, models, a Laravel-style validator such as `'email' => 'required|email|max:255'`), but every piece is a short file you can read top to bottom. Nothing is hidden behind framework magic, which makes it easier to explain.

### Product vs. offer: the key idea

- A **product** is the thing being sold: *"Elden Ring, Steam, GLOBAL"*. It has a name, images, platform, region and a list price, but **no price of its own**.
- An **offer** is *one seller selling that product*: seller + price. A product can have several offers (Elden Ring has 4).
- **Stock** isn't a number in a column. It's the count of `available` rows in `digital_keys` for that offer, so stock can never drift from the real keys.
- The price on a product card ("From €33.99") is the cheapest offer that has stock, computed in SQL.
- `cart_items` and `order_items` point to an **offer**, so the system always knows which seller you picked. Order items also save a copy of the name, seller and price at the moment of purchase, so later price changes don't rewrite your order history.

### How a key can never be sold twice

Payment (`CheckoutService::pay`) runs in **one database transaction**:

1. `SELECT … FOR UPDATE` locks the order row, so double-clicking *Pay* can't pay twice.
2. For each line, `SELECT … FROM digital_keys WHERE status='available' … FOR UPDATE SKIP LOCKED` locks enough free keys. Two buyers paying at the same moment skip each other's locked rows, so they get different keys.
3. The fake card is charged.
4. The keys are updated to `sold` with `WHERE status='available'`, and if fewer rows change than expected, everything is rolled back.

The database adds a further layer of safety: `code` is `UNIQUE`, each key has a single `order_item_id` column, and a `CHECK` forces "sold ⇔ has an order item". If the payment is declined, nothing is written and the locks are released.

### Typo-tolerant search

PostgreSQL's `pg_trgm` extension splits text into 3-letter pieces (`cyb`, `ybe`, `ber`…). Names that share many pieces with the query count as similar, so `cyberpnk` still finds *Cyberpunk 2077*. `SearchService` combines `ILIKE '%query%'` (exact substrings) with `word_similarity() >= 0.3` (fuzzy). Results are ranked as: names that start with the query first, then substring matches, then the best fuzzy score. A GIN trigram index keeps it fast.

---

## Running locally

### Requirements

- PHP **8.1+** with the `pdo_pgsql` extension (no Composer needed)
- PostgreSQL **13+** (needs the `pg_trgm` extension, which ships with PostgreSQL)
- Node.js **20+**

### 1. Database

```bash
# create a user and two databases (the second one is used by the tests and gets wiped on every test run)
sudo -u postgres psql -c "CREATE USER keyvault WITH PASSWORD 'keyvault';"
sudo -u postgres createdb -O keyvault keyvault
sudo -u postgres createdb -O keyvault keyvault_test
```

On Windows or macOS you can run the same SQL in pgAdmin / `psql`, or start PostgreSQL in Docker:
`docker run -d --name kv-pg -p 5432:5432 -e POSTGRES_USER=keyvault -e POSTGRES_PASSWORD=keyvault -e POSTGRES_DB=keyvault postgres:16`
(then also run `CREATE DATABASE keyvault_test;`).

### 2. Backend

```bash
cd backend
cp .env.example .env                       # edit DB_* if your credentials differ
php database/migrate.php --fresh --seed    # create tables + sample data
php -S 127.0.0.1:8000 -t public public/index.php
```

Check it: <http://127.0.0.1:8000/api/health> should return `{"status":"ok"}`.

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

Open <http://localhost:5173>. Vite forwards `/api` and `/images` to the PHP server on port 8000 (see `vite.config.ts`), so the browser sees a single origin.

### Database commands

| Command | What it does |
|---|---|
| `php database/migrate.php` | Runs migrations that haven't run yet |
| `php database/migrate.php --fresh --seed` | Drops everything, recreates the tables, inserts sample data |
| `php database/migrate.php --seed-if-empty` | Migrates, and seeds only if there are no products yet (used on deploy) |

The seed is deterministic (fixed random seed), so every run produces the same products, offers and keys.

## Tests

### API tests (37)

```bash
cd backend
php tests/run.php              # all tests
php tests/run.php Checkout     # only test classes whose name contains "Checkout"
```

Every test runs against a freshly migrated and seeded `keyvault_test` database, and requests go straight into `App::handle()` (no web server needed), so the tests exercise the real router, validation, services and SQL.

| File | Covers |
|---|---|
| `AuthTest` | register (incl. duplicate email and validation), login, wrong password, `/me`, logout revokes the token, protected routes need a token |
| `CatalogTest` | partial-name search, typo search, no-match search, suggestions, filters (platform, region, multiple categories, price range), sorting, server-side pagination, invalid filters → 422, product with several offers, unknown product → 404, homepage sections |
| `CartTest` | guest cart remembered via token, adding the same offer twice, update and remove, stock limit, validation, guest cart merged on login, can't touch another user's cart lines |
| `CheckoutTest` | successful payment assigns keys, declined payment assigns none, can't pay twice, empty cart, card validation, **the same key is never sold to two users**, an already-sold key can't be reassigned |
| `OrderAccessTest` | can't view or pay another user's order, order list only shows your own orders, the owner sees their keys |

### Browser smoke test

`e2e/smoke_test.py` drives a real browser through the whole journey: homepage, typo search, filters, product, add an offer as a guest, refresh the cart, log in (cart merges), declined payment, successful payment, keys, order history, someone else's order, logout, and the mobile layout.

```bash
pip install playwright && playwright install chromium
python e2e/smoke_test.py http://127.0.0.1:5173
```

## API reference

All responses are JSON. Errors look like `{"message": "...", "errors": {"field": ["..."]}}`. Money values are integer **cents**.

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/health` | | API + DB health check |
| POST | `/api/auth/register` | | `{name, email, password}` → 201 `{user, token}` |
| POST | `/api/auth/login` | | `{email, password}` → `{user, token}` · 401 on bad credentials |
| POST | `/api/auth/logout` | ✔ | Revokes the token → 204 |
| GET | `/api/auth/me` | ✔ | Current user |
| GET | `/api/products` | | `?q= &category= &platform= &region= &price_min= &price_max= &in_stock=1 &sort= &page= &per_page=` (lists comma-separated) |
| GET | `/api/products/{slug}` | | Product detail with images, offers and related products · 404 |
| GET | `/api/filters` | | Filter values with counts + price range |
| GET | `/api/categories` | | Categories with product counts |
| GET | `/api/home/{section}` | | `popular` · `deals` · `new` · `recommended` · `featured` |
| GET | `/api/search/suggest?q=` | | Up to 6 quick matches |
| GET | `/api/cart` | | Current cart (guest: `X-Cart-Token` header) |
| POST | `/api/cart/items` | | `{offer_id, quantity}` → 201 cart · 404 unknown offer · 409 not enough stock · 422 |
| PATCH | `/api/cart/items/{id}` | | `{quantity}` → cart |
| DELETE | `/api/cart/items/{id}` | | → cart |
| POST | `/api/checkout` | ✔ | Cart → **pending** order (prices frozen) → 201 |
| POST | `/api/orders/{id}/pay` | ✔ | `{card_number, card_name, expiry, cvc}` → 200 paid · **402** declined · 409 already paid / sold out · 422 invalid card |
| GET | `/api/orders` | ✔ | Your orders |
| GET | `/api/orders/{id}` | ✔ | Your order with keys (if paid) · 404 if it isn't yours |

Auth = `Authorization: Bearer <token>` from login/register.

---

## Deployment

The app ships as **one Docker image**: a Node stage builds the React app, and the final `php:8.3-apache` image serves the React files and the API from the same folder. Apache rules (`docker/apache.conf`):

- `/api/*` goes to `index.php`
- real files (JS/CSS bundles, product images) are served directly
- everything else goes to `index.html`, so React Router URLs like `/product/elden-ring-steam-global` work on refresh

One origin means no CORS setup in production. On start-up, `docker/start.sh` runs `migrate.php --seed-if-empty`, so the first deploy fills the database and later deploys keep the data.

### Deploy on Render (free)

1. Push this repository to GitHub.
2. In Render: **New → Blueprint**, then pick the repository. `render.yaml` creates a free PostgreSQL database (`keyvault-db`) and the web service, and connects them via `DATABASE_URL`.
3. Wait for the first build (~3–5 min). Open the service URL and check `/api/health`.

Notes: free Render services sleep after ~15 minutes without traffic (the first request after that takes ~30–60 s), and free Render PostgreSQL databases expire after 30 days. To keep the data longer, create a free database on another provider (e.g. Neon) and set `DATABASE_URL` on the service to its connection string (add `?sslmode=require`).

### Any other Docker host

```bash
docker build -t keyvault .
docker run -p 8080:80 -e DATABASE_URL='postgres://user:pass@host:5432/dbname' keyvault
```

`PORT` is respected if the host sets it.

### Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `DATABASE_URL` | | `postgres://user:pass@host:port/db` (takes priority over `DB_*`) |
| `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASSWORD` | `127.0.0.1` `5432` `keyvault` `keyvault` | Local connection |
| `DB_SSLMODE` | `prefer` | e.g. `require` for hosted databases |
| `DB_TEST_NAME` | `keyvault_test` | Database used (and wiped) by the tests |
| `CORS_ORIGINS` | `http://localhost:5173` | Only needed if the frontend is hosted on a different domain |
| `APP_DEBUG` | `true` locally, `false` in Docker | Shows exception messages in 500 responses |
| `VITE_API_URL` | empty (same origin) | Frontend build-time: API base URL if it lives elsewhere |

## Known limitations

- No real payment provider, email delivery, seller dashboard, reviews or wishlist. Those are outside the scope of this project.
- Product images are generated SVG placeholders (no copyrighted game art).
- Login tokens don't expire on their own (they're removed on logout).
