"""Browser smoke test: clicks through the main user journey and saves screenshots.

Usage:  pip install playwright && playwright install chromium
        python e2e/smoke_test.py [base_url]   (default http://127.0.0.1:5173)
Needs the seeded demo account. Prints what it sees at each step."""
import os; os.makedirs("screenshots", exist_ok=True)
import sys, time
from playwright.sync_api import sync_playwright

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:5173"
errors = []

def run(page, name):
    page.screenshot(path=f"screenshots/{name}.png", full_page=False)

with sync_playwright() as p:
    b = p.chromium.launch(args=["--no-sandbox"])
    ctx = b.new_context(viewport={"width": 1366, "height": 860})
    page = ctx.new_page()
    page.on("console", lambda m: m.type == "error" and errors.append(m.text))
    page.on("pageerror", lambda e: errors.append(str(e)))

    page.goto(BASE + "/"); page.wait_for_selector(".card:not(.card--skeleton)"); time.sleep(1)
    run(page, "01-home")
    print("home sections:", page.locator(".section h2").all_inner_texts())
    print("cards on home:", page.locator(".card:not(.card--skeleton)").count())

    # search suggestions with a typo
    page.fill(".header__search input", "witchr")
    page.wait_for_selector(".suggestion", timeout=5000); time.sleep(.3)
    print("suggestions for 'witchr':", page.locator(".suggestion__name").all_inner_texts())
    run(page, "02-suggest")
    page.keyboard.press("Enter")
    page.wait_for_url("**/store?q=witchr"); page.wait_for_selector(".grid .card__title")
    print("store results for witchr:", page.locator(".grid .card__title").all_inner_texts())

    # store with filters
    page.goto(BASE + "/store"); page.wait_for_selector(".grid .card__title")
    total0 = page.locator(".store__head p").inner_text()
    page.locator("fieldset.filter-group", has_text="Platform").locator("label.check", has_text="Steam").locator("input").click(); time.sleep(.8)
    page.select_option(".select select", "price_asc"); time.sleep(1)
    print("store:", total0, "->", page.locator(".store__head p").inner_text(), "| url:", page.url)
    prices = page.locator(".grid .card__price strong").all_inner_texts()
    print("first prices asc:", prices[:6])
    page.fill('input[aria-label="Maximum price"]', "20"); time.sleep(1.5)
    print("max 20€:", page.locator(".store__head p").inner_text(), page.url)
    run(page, "03-store")

    # product page with multiple offers
    page.goto(BASE + "/product/elden-ring-steam-global"); page.wait_for_selector(".offer")
    print("offers:", page.locator(".offer").count(), page.locator(".offer__price strong").all_inner_texts())
    run(page, "04-product")
    # add the SECOND (more expensive) offer as guest
    page.locator(".offer").nth(1).get_by_role("button", name="Add to cart").click()
    page.wait_for_selector(".toast"); time.sleep(.5)
    print("toast:", page.locator(".toast").first.inner_text())

    # cart survives refresh
    page.goto(BASE + "/cart"); page.wait_for_selector(".cart-line")
    page.reload(); page.wait_for_selector(".cart-line")
    print("cart after refresh:", page.locator(".cart-line__info").first.inner_text().replace("\n", " | "))
    page.get_by_label("Increase").click(); time.sleep(.8)
    print("qty now:", page.locator(".qty span").inner_text(), "total:", page.locator(".summary__total strong").inner_text())
    run(page, "05-cart")

    # login (guest cart should merge)
    page.get_by_role("button", name="Log in to checkout").click()
    page.wait_for_url("**/login")
    page.fill('input[type=email]', "demo@keyvault.test"); page.fill('input[type=password]', "password123")
    page.get_by_role("button", name="Log in", exact=True).click()
    page.wait_for_url("**/checkout"); page.wait_for_selector(".review-line")
    print("checkout lines after login:", page.locator(".review-line").count())
    run(page, "06-checkout")
    page.get_by_role("button", name="Continue to payment").click()
    page.wait_for_url("**/checkout/pay/*"); page.wait_for_selector(".pay-form")

    # declined card first
    page.locator(".test-card").nth(1).click()
    page.get_by_role("button", name="Pay €").click()
    page.wait_for_selector(".pay-result--failed")
    print("decline:", page.locator(".pay-result--failed").inner_text().replace("\n", " | "))
    run(page, "07-declined")
    # then success
    page.locator(".test-card").nth(0).click()
    page.get_by_role("button", name="Pay €").click()
    page.wait_for_url("**/orders/*?success=1"); page.wait_for_selector(".key")
    page.locator(".key .link-btn", has_text="Reveal").first.click()
    print("keys:", page.locator(".key code").all_inner_texts())
    run(page, "08-success")
    print("cart badge after purchase:", page.locator(".cart-btn .badge").count())

    page.goto(BASE + "/account"); page.wait_for_selector(".order-row")
    print("orders:", page.locator(".order-row").all_inner_texts()[:2])
    run(page, "09-account")

    # another user's order -> 404 page
    page.goto(BASE + "/orders/999999"); page.wait_for_selector(".empty")
    print("foreign order:", page.locator(".empty h3").inner_text())

    # logout
    page.goto(BASE + "/account"); page.get_by_role("button", name="Log out").first.click()
    page.wait_for_url(BASE + "/"); time.sleep(.5)
    print("logged out, login link visible:", page.get_by_role("link", name="Log in").count() > 0)

    # mobile
    m = b.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True, device_scale_factor=2)
    mp = m.new_page(); mp.on("pageerror", lambda e: errors.append(str(e)))
    mp.goto(BASE + "/"); mp.wait_for_selector(".card:not(.card--skeleton)"); time.sleep(1)
    mp.screenshot(path="screenshots/10-mobile-home.png")
    mp.goto(BASE + "/store"); mp.wait_for_selector(".grid .card"); mp.screenshot(path="screenshots/11-mobile-store.png")
    mp.locator(".store__filter-toggle").click(); time.sleep(.5); mp.screenshot(path="screenshots/12-mobile-filters.png")
    mp.goto(BASE + "/product/elden-ring-steam-global"); mp.wait_for_selector(".offer"); time.sleep(.5)
    mp.screenshot(path="screenshots/13-mobile-product.png", full_page=True)
    sw = mp.evaluate("document.documentElement.scrollWidth")
    print("mobile horizontal overflow:", sw, "> 390" if sw > 390 else "none")

    print("JS errors:", errors or "none")
    b.close()
