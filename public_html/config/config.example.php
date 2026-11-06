<?php
// copy me to config.php and fill in the blanks. config.php is ignored by git so your secrets stay on your server
// ***  DEBUG *** //
//error_reporting(E_ALL);
//ini_set('display_errors', 1); // turn back on when debugging, it leaks paths and sql on a live site
ini_set('display_errors', 0);
ini_set('log_errors', 1);
//ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/error.log');
// *** ------ *** //

define('APP_ROOT', '/'); 

// -- Main DB -- //
define('DB_HOST', 'localhost'); 
define('DB_USER', '');
define('DB_PASSWORD', '');
define('DB_NAME', 'ib_store');

//manually check if session exired
define('CHECK_SESSION_EXPIRED', FALSE);
define('SESSION_LIMIT', 1440); //1440 defualt

// -- Request Rate Limit -- //
// Recomended to use redis
// Includes file based fall back
define('THROTTLE', TRUE); 

// (Rate / 60 sec) per ipaddress req
define('REQUEST_LIMIT', 280);

// sign in, register, forgot and reset: tries per window (seconds), counted per visitor and per account email
define('AUTH_LIMIT', 10);
define('AUTH_WINDOW', 900);

// the reverse proxy's address when one sits in front of apache (REMOTE_ADDR is then the proxy for every
// visitor). requests from it are counted by the X-Forwarded-For address it adds. empty = no proxy
define('TRUSTED_PROXY', '');

// -- Mail -- //
// php mail() through the server's mta. only the from address is ours to pick, empty = mail off
define('MAIL_FROM', 'noreply@example.com'); 
define('MAIL_FROM_NAME', ''); // defaults to the store name when empty

// -- Site Address -- //
// the store's public address, no trailing slash. links in emails (password reset, orders, downloads) and the
// payment return page are built from this. empty falls back to the request's host header, which a visitor can
// fake: fine on a test box, set it on a live store
define('BASE_URL', 'https://store.example.com');

// -- Downloads -- //
// digital products' files, outside public_html so apache cannot hand them out. a product's download field is a
// file name in here (or a full https:// link for a file hosted elsewhere), /user/orders/<uuid>/download/<line>
// streams it to the owner of a paid order. default is a downloads/ folder next to public_html
define('DOWNLOAD_DIR', dirname(__DIR__, 2) . '/downloads/');

// -- Uploads -- //
// where admin product images land, relative to public_html
define('ITEM_IMAGE_DIR', 'assets/images/items/');
define('ITEM_IMAGE_MAX', 4 * 1024 * 1024); // 4mb

// -- Payments: BTCPay Server -- //
// self hosted bitcoin, see docs/README.md. leave BTCPAY_URL empty and the bitcoin option stays off the payment page.
// api key needs btcpay.store.cancreateinvoice + canviewinvoices on the store, the webhook secret is what btcpay shows
// when you add a webhook pointing at https://<store>/api/payment/btcpay/webhook
define('BTCPAY_URL', ''); // https://btcpay.example.com
define('BTCPAY_API_KEY', ''); 
define('BTCPAY_STORE_ID', ''); 
define('BTCPAY_WEBHOOK_SECRET', ''); 

// -- Payments: Stripe -- //
// cards through stripe checkout. secret key from the stripe dashboard (sk_test_... to try it, sk_live_... for real),
// webhook secret from Developers > Webhooks after adding https://<store>/api/payment/stripe/webhook with the
// checkout.session.completed and checkout.session.async_payment_succeeded events. empty key = card option off.
// an empty webhook secret refuses the webhook, payments then settle when the customer lands back on the store
define('STRIPE_SECRET_KEY', ''); 
define('STRIPE_WEBHOOK_SECRET', ''); 

// -- Payments: PayPal -- //
// client id and secret from a rest app at developer.paypal.com. PAYPAL_SANDBOX true uses the sandbox api, false the
// live one. PAYPAL_WEBHOOK_ID is optional: add a webhook for https://<store>/api/payment/paypal/webhook with the
// PAYMENT.CAPTURE.COMPLETED and CHECKOUT.ORDER.APPROVED events and paste its id. without it the store still
// captures when the customer lands back on the payment page. empty client id = paypal option off
define('PAYPAL_CLIENT_ID', ''); 
define('PAYPAL_SECRET', ''); 
define('PAYPAL_SANDBOX', TRUE); 
define('PAYPAL_WEBHOOK_ID', ''); 

// -- Password Reset -- //
// how long a reset link is good for (seconds)
define('RESET_LIFE', 3600);