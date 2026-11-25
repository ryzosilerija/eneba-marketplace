-- KeyVault database schema (PostgreSQL)
-- All money values are stored as integers in cents to avoid floating point rounding errors.

CREATE EXTENSION IF NOT EXISTS pg_trgm; -- trigram matching for typo-tolerant search

CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Bearer tokens for API auth. Only a SHA-256 hash of the token is stored.
CREATE TABLE api_tokens (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_used_at TIMESTAMPTZ
);

CREATE TABLE categories (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
);

-- A product is the thing being sold (e.g. "Cyberpunk 2077 Steam Key GLOBAL").
-- It has NO price of its own: prices come from sellers' offers.
CREATE TABLE products (
    id             SERIAL PRIMARY KEY,
    slug           VARCHAR(160) NOT NULL UNIQUE,
    name           VARCHAR(160) NOT NULL,
    description    TEXT NOT NULL,
    category_id    INT NOT NULL REFERENCES categories(id),
    platform       VARCHAR(40) NOT NULL,   -- Steam, PlayStation, Xbox, Nintendo, ...
    region         VARCHAR(40) NOT NULL,   -- GLOBAL, EUROPE, NORTH AMERICA
    developer      VARCHAR(120),
    publisher      VARCHAR(120),
    release_date   DATE,
    original_price INT NOT NULL,           -- publisher's list price (cents), used to show discount %
    rating         NUMERIC(2,1) NOT NULL DEFAULT 0,
    sales_count    INT NOT NULL DEFAULT 0, -- used for "popular" sorting
    is_featured    BOOLEAN NOT NULL DEFAULT FALSE,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX products_name_trgm ON products USING gin (name gin_trgm_ops);
CREATE INDEX products_filters ON products (platform, region, category_id);

CREATE TABLE product_images (
    id         SERIAL PRIMARY KEY,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    url        VARCHAR(255) NOT NULL,
    position   INT NOT NULL DEFAULT 0      -- 0 = cover image
);

CREATE TABLE sellers (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    country     VARCHAR(60),
    rating      NUMERIC(3,1) NOT NULL DEFAULT 0, -- 0..100 (% positive feedback)
    sales_count INT NOT NULL DEFAULT 0,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- An offer = one seller selling one product at their own price.
-- Stock is not a column: it is the number of 'available' digital keys of the offer.
CREATE TABLE offers (
    id         SERIAL PRIMARY KEY,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    seller_id  INT NOT NULL REFERENCES sellers(id) ON DELETE CASCADE,
    price      INT NOT NULL CHECK (price > 0),
    is_active  BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (product_id, seller_id)
);

CREATE TABLE carts (
    id         SERIAL PRIMARY KEY,
    token      CHAR(36) UNIQUE,                -- guest carts are identified by a random UUID
    user_id    INT UNIQUE REFERENCES users(id) ON DELETE CASCADE, -- one cart per user
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- A cart line points at an OFFER (product + chosen seller), not just a product.
CREATE TABLE cart_items (
    id       SERIAL PRIMARY KEY,
    cart_id  INT NOT NULL REFERENCES carts(id) ON DELETE CASCADE,
    offer_id INT NOT NULL REFERENCES offers(id) ON DELETE CASCADE,
    quantity INT NOT NULL CHECK (quantity BETWEEN 1 AND 10),
    UNIQUE (cart_id, offer_id)
);

CREATE TABLE orders (
    id                SERIAL PRIMARY KEY,
    user_id           INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status            VARCHAR(20) NOT NULL DEFAULT 'pending'
                      CHECK (status IN ('pending', 'paid', 'failed')),
    total             INT NOT NULL,
    payment_reference VARCHAR(64),
    failure_reason    VARCHAR(255),
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    paid_at           TIMESTAMPTZ
);
CREATE INDEX orders_user ON orders (user_id, created_at DESC);

-- Order lines keep a snapshot of name/seller/price so later price changes don't rewrite history.
CREATE TABLE order_items (
    id           SERIAL PRIMARY KEY,
    order_id     INT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    offer_id     INT REFERENCES offers(id) ON DELETE SET NULL,
    product_id   INT REFERENCES products(id) ON DELETE SET NULL,
    product_name VARCHAR(160) NOT NULL,
    seller_name  VARCHAR(100) NOT NULL,
    platform     VARCHAR(40) NOT NULL,
    region       VARCHAR(40) NOT NULL,
    unit_price   INT NOT NULL,
    quantity     INT NOT NULL CHECK (quantity > 0)
);

-- Digital keys (fictional test codes). A key is either available or sold to exactly one order item.
-- UNIQUE(code) prevents duplicate codes; the single order_item_id column + the
-- "WHERE status = 'available'" guard in CheckoutService prevent double assignment.
CREATE TABLE digital_keys (
    id            SERIAL PRIMARY KEY,
    offer_id      INT NOT NULL REFERENCES offers(id) ON DELETE CASCADE,
    code          VARCHAR(64) NOT NULL UNIQUE,
    status        VARCHAR(20) NOT NULL DEFAULT 'available' CHECK (status IN ('available', 'sold')),
    order_item_id INT REFERENCES order_items(id), -- no cascade: a sold key must never silently become "unsold"
    sold_at       TIMESTAMPTZ,
    CHECK ((status = 'sold') = (order_item_id IS NOT NULL))
);
CREATE INDEX digital_keys_available ON digital_keys (offer_id) WHERE status = 'available';
