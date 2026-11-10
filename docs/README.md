# iB Store

A general store front on Flight PHP + FlightJS. Physical and digital products, cart, checkout, customer accounts, an admin panel and payments through BTCPay Server, Stripe Checkout and PayPal. The root README has the short install, this is the longer read.

## Install

1. Apache with mod_rewrite pointing at `public_html` (the `.htaccess` is there), PHP 8.1 or newer with pdo_mysql, mbstring, fileinfo. Redis + the php redis extension are optional (rate limiting falls back to files in `cache/req/`).
2. Import `ib_store.sql` into a MariaDB / MySQL database.
3. Copy `public_html/config/config.example.php` to `config.php`, fill in `DB_*`. Leave `display_errors` off on a live server. `config.php` is in `.gitignore`.
4. Mail goes out through php `mail()` and the server's MTA. `MAIL_FROM` in config is the from address. Set it empty and the forgot password page says mail is off instead of failing.
5. Make `cache/` and `assets/images/items/` writable by the web user. `cache/` holds the class map and the rate limit files, `assets/images/items/` is where admin product uploads land.
6. Set `BASE_URL` to the store's public address, emailed links are built from it. Files for digital products go in `downloads/` next to `public_html` (`DOWNLOAD_DIR`) where Apache cannot serve them, the store hands them to paid orders through `/user/orders/<uuid>/download/<line>/<n>`. A product's download field takes several files separated by commas (one build per platform), the order page and the paid email link each one by its file name. A digital product can also hand out keys (itch.io download key links, Steam keys, any code): paste them on the admin product page and / or pick a .txt file of them one per line for a long list, and one is held per order at checkout, like physical stock, shown on the order page and in the paid email, and put back if the order is cancelled before it is paid (a paid order's key has been seen, so it stays with that order). Taking an order back out of cancelled holds keys for it again, or refuses when the product ran out. Like physical stock, a key waits on a pending order until it is paid or cancelled. What a digital product hands out is the two boxes under Digital download on its admin page, Download files and Digital key, either or both (`products.use_files`, `products.use_keys`). Unticking one stops handing it out and keeps what was entered. A product handing out keys shows sold out when they run out. Behind a reverse proxy, put its address in `TRUSTED_PROXY` so the rate limits count visitors instead of the proxy.
7. Register, set `is_admin = 1` on that account in the `accounts` table, and open `/admin`. Set the currency, tax rate and shipping in Store Settings first.

The class map cache (`cache/class_map.json`) rebuilds itself every 24 hours or when deleted. Delete it after moving the site or adding classes if something cannot be found.

## How it hangs together

- `routes.php` maps every url to a controller method. Page routes render a view, `/api/*` routes answer json.
- Page controllers extend `BaseController`. `setGlobals()` loads what every page needs (store name, categories, cart count, signed in user). `requireLogin()` and `requireAdmin()` guard pages. `csrfToken()` puts a form token on the page, forms send it back as `csrf_token` (see `form_handler.js`, it reads `data-csrf` off the submit button).
- Api controllers extend `controllers/api/ApiController.php`: `input()` reads the posted json, `respond()` wraps the work and turns a thrown `Exception` into `{success: false, error}` for the user (anything else is logged and comes back as a generic message). `ProductsApi` and `StoreApi` predate it and keep their own `respond()`.
- Models are one class per table with static finders, `fill($data)` for validating posted data and `save()`. `AddressesModel` covers both `account_billing` and `account_shipping` (same columns now), the `$type` picks the table.
- Libraries: `CartLibrary` (session cart, totals), `PaymentLibrary` and the three gateways under it, `OrderMailLibrary`, `MailLibrary` (php mail() plus the html card the emails use), `SessionLibrary`, `DatabaseLibrary`, `ViewLibrary`, `NonceLibrary` (not used by anything yet).
- Views are plain php + html in `views/`. Shared bits live in `views/include/` (menu, footer, address fields, order summary) and `views/admin/include/` (admin shell).
- FlightJS: `assets/js/routes.js` maps the url to a page class in `assets/js/pages/` plus its config. `global_ui.js` handles the menu, the search box and every Add to Cart button on the site. `tools/api_form.js` is `form_handler.js` with the usual endings filled in (toast the message, follow a redirect). `tools/file_upload.js` is the same idea for a form with a file in it (multipart): the product images, the banner and the tab icon all go through it. `tools/tablesorter.js` drives the admin tables against `/api/admin/*/list`.

## Flow of a sale

1. Add to Cart posts `/api/cart/add`. The cart is `product_id => qty` in the session, prices are looked up fresh each time. Digital items are one per order, physical ones are capped by stock.
2. `/cart` renders from `/api/cart/items`. Totals: subtotal, flat shipping when something physical is in the cart (waived over `free_shipping_over`), tax as a percent of the subtotal. All three come from `store_info`.
3. `/cart/checkout` needs an account (signin bounces back to it). Billing address always, shipping only when something needs shipping. The addresses are saved as the account defaults.
4. Place Order posts `/api/cart/checkout`: a `pending` order is written with the addresses and the lines copied in (so later edits to a product or an address never change an old order), physical stock comes off, the cart is cleared, and the customer lands on `/cart/payment/<uuid>`. With mail set up the customer gets a confirmation and the store address gets a heads up; later status changes (paid, shipped, complete, cancelled, refunded) mail the customer too, and paid mails the store as well. All of it lives in `libraries/OrderMailLibrary.php`. Admin > Store Settings has a test email button.
5. Payment: `/cart/payment/<uuid>` lists the gateways that are switched on (Bitcoin through BTCPay Server, cards through Stripe Checkout, PayPal): keys filled in config and not switched off under Store Settings > Payments, which is `store_info.payments_off`. Switching one off only stops new payments, one already started still settles. A gateway button posts `/api/payment/<gateway>/start`, the store creates the invoice over there, stores its id in `orders.payment_ref` with `payment_method`, and sends the customer to the gateway's checkout. The gateway's webhook (`/api/payment/<gateway>/webhook`, signature checked) calls `OrdersModel::markPaid()`, which only flips a `pending` order so a repeated webhook is harmless. Coming back to the payment page, `payment.js` asks `/api/payment/<gateway>/status`, which also marks the order paid if the webhook never arrived. Downloads for digital lines show on the customer's order page once the status is `paid`, `shipped` or `complete`.
6. Admin: orders list with a status filter, one order with a status select and private notes. Cancelling a pending order puts the stock back.

## Bitcoin (BTCPay Server)

Self hosted, no middleman, the coins go to your own wallet. Run BTCPay Server (Docker, their docs cover it) somewhere the store can reach over https, then:

1. In BTCPay make a store, connect a wallet (xpub), and under Account > API Keys make a key with `btcpay.store.cancreateinvoice` and `btcpay.store.canviewinvoices` for that store.
2. Store Settings > Webhooks: add one with the payload url `https://<your store>/api/payment/btcpay/webhook`, all events or at least Invoice settled / expired / invalid, and copy the secret.
3. Fill in `BTCPAY_URL`, `BTCPAY_API_KEY`, `BTCPAY_STORE_ID` (the store id from BTCPay's url) and `BTCPAY_WEBHOOK_SECRET` in `config/config.php`. The Pay with Bitcoin button appears on the payment page as soon as all three of the first ones are set.
4. Confirmation policy (0-conf, 1 confirmation, more) is the BTCPay store's setting; the invoice asks for `MediumSpeed` (one confirmation) and the store setting wins. Invoices expire after 30 minutes, a fresh one is made when the customer clicks again.

The client library lives in `public_html/assets/vendor/BTCPayServer/` (the official `btcpayserver-greenfield-php`, vendored the same way as `flight/`, needs the curl, json, mbstring and bcmath extensions). `./update-libs.sh` pulls the newest tagged release into place; `assets/vendor/BTCPayServer/VERSION` says which one is there. `libraries/BtcpayLibrary.php` is the thin store-side wrapper, `controllers/api/PaymentApiController.php` holds the start / status / webhook actions, the same three for every gateway. Each gateway is a `*Library` extending `libraries/PaymentLibrary.php` (`enabled`, `start`, `check`, `webhook`), one line in `PaymentLibrary::all()`, an icon on `views/cart/payment.html` and a block in config. `PaymentLibrary::settle()` is where an order becomes paid: `OrdersModel::markPaid()` (one guarded update, pending only, so a repeated webhook or a webhook racing the status check is a no-op) then `OrderMailLibrary::statusChanged()` for the paid emails.

## Cards (Stripe Checkout)

Hosted Stripe page, so card numbers never touch the server. Make a Stripe account, copy the secret key (`sk_test_...` to try it, `sk_live_...` for real) into `STRIPE_SECRET_KEY`. Under Developers > Webhooks add `https://<your store>/api/payment/stripe/webhook` with the `checkout.session.completed` and `checkout.session.async_payment_succeeded` events and put its signing secret in `STRIPE_WEBHOOK_SECRET`. The session is itemised (lines, shipping, tax) so the Stripe receipt matches the order, expires after an hour, and the webhook signature is checked with a five minute tolerance. No SDK: `libraries/StripeLibrary.php` is three REST calls on curl.

## PayPal

A REST app at developer.paypal.com gives a client id and secret, those go in `PAYPAL_CLIENT_ID` and `PAYPAL_SECRET`; `PAYPAL_SANDBOX` picks the sandbox or live api. The customer approves on PayPal, lands back on the payment page, and the store captures the payment there (`status` call). A webhook is optional backup for the customer who approves and closes the tab: add `https://<your store>/api/payment/paypal/webhook` with `CHECKOUT.ORDER.APPROVED` and `PAYMENT.CAPTURE.COMPLETED` and put its id in `PAYPAL_WEBHOOK_ID`; the store verifies deliveries through PayPal's own verify call, so without the id the webhook is ignored. No SDK here either (PayPal retired theirs), `libraries/PaypalLibrary.php` is Orders v2 on curl.

## Accounts

- Register: `/register` -> `/api/auth/register`, argon2id password, signs them in and emails a `/verify/<id>/<token>` link that sets `verified`. The token is an hmac of the id and email keyed on the password hash, nothing is stored, and it stops working when either changes.
- Sign in / out: `/signin`, `/signout`. One login session per account (`login_sessions` is keyed on `user_id`), `AccountsModel::resume()` checks it on every page.
- Forgot: `/forgot` emails a link to `/reset/<token>`. Tokens are sha256 hashed in `password_resets`, last `RESET_LIFE` seconds (an hour), one live token per account. A reset also drops the account's login session.
- Settings: `/user/settings` for username, email, password and the two addresses. Orders at `/user/orders`.
- Remember me: the checkbox only keeps the email in localStorage (`signin.js`). The old cookie code is commented out in `AccountsModel` and `AuthController`.

## Admin

`/admin` is for accounts with `is_admin = 1`. Dashboard, orders (with a CSV export of whatever the search and status filter show, through `tools/export_csv.js` and `POST /api/admin/orders/export`), products (with image upload, first image is the card image, and a description written in markdown in an EasyMDE editor, from `assets/vendor/easymde/` by `./update-libs.sh`, a plain box when it is missing. `libraries/MarkdownLibrary.php` renders what its toolbar writes on the product page), categories, accounts (flags and staff notes, you cannot un-admin yourself), store settings (contact details, currency, tax, shipping, the tab icon, the home page banner: image, heading, a line and a button, each optional. No image, no banner, and an on / off switch for each payment gateway with keys). Everything saves through `POST /api/admin/<section>/<action>`.

Product images are checked by content type (jpg, png, webp, gif, 4 MB cap in config) and saved as `assets/images/items/<slug>-<n>.<ext>`. Deleting a product or an image deletes the file too.

## Local testing

The root README's "Try it locally" has the whole recipe: database, config, and php's built in server. The server part is `php -S 127.0.0.1:8085 -t public_html router.php` with this `router.php` next to `public_html`, which does what the `.htaccess` does:

    <?php
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/' && is_file(__DIR__ . '/public_html' . $path)) return false;
    $_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/public_html';
    $_SERVER['SCRIPT_NAME'] = '/index.php';   // php -S sets this to the request path, which makes Flight think the first segment is a base dir
    $_SERVER['PHP_SELF'] = '/index.php';
    chdir(__DIR__ . '/public_html');
    require __DIR__ . '/public_html/index.php';
