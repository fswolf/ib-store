# iB Store

A small self hosted store front in plain PHP. Physical and digital products, cart, checkout, customer accounts, order emails, an admin panel, and three payment options: Bitcoin through your own BTCPay Server, cards through Stripe Checkout, and PayPal. Built on [Flight PHP](https://flightphp.com/) with a matching FlightJS front end, no Composer, no build step. Clone it, point Apache at `public_html`, import the database and you have a store.

## What is in the box

- **Catalogue** with categories, product images, stock, badges (new, featured, sale) and search
- **Cart and checkout** with flat shipping, free shipping over an amount, and a tax percent, all set from the admin panel
- **Digital products** that skip shipping and show a download link once the order is paid
- **Accounts** with argon2id passwords, password reset by email, saved billing and shipping addresses, and an order history
- **Order emails** on checkout and on every status change (paid, shipped, complete, cancelled, refunded), through the server's own `mail()`
- **Admin panel** for orders, products, categories, accounts and store settings, with a test email button
- **Payments** behind one small interface, so each gateway is a single class. Switch any of them on by filling in its keys in the config, leave them empty and the option stays off the payment page

## Requirements

- Apache with `mod_rewrite` (the `.htaccess` is in `public_html`), or anything else that can send every request to `index.php`
- PHP 8.1 or newer with `pdo_mysql`, `mbstring`, `curl`, `json` and `fileinfo`. Bitcoin also needs `bcmath`
- MariaDB or MySQL
- A working `mail()` on the server for the order emails (optional, the store runs without it)
- Redis and the php redis extension for rate limiting (optional, falls back to files)

## Install

1. Clone the repo and point your web root at `public_html`.
2. Create a database and import `ib_store.sql`. It has the tables, a demo category with a few products, and one store settings row.
3. Copy `public_html/config/config.example.php` to `config.php` and fill in the `DB_*` lines and `MAIL_FROM`. That file is ignored by git, so your keys never leave the server.
4. Make `public_html/cache/` and `public_html/assets/images/items/` writable by the web server user. The first holds the class map and rate limit files, the second is where product uploads land.
5. Register an account, then set `is_admin = 1` on it in the `accounts` table and open `/admin`. Store Settings is the first stop: currency, tax, shipping and your contact details.
6. For Bitcoin, run `./update-libs.sh` once. It pulls the official BTCPay Greenfield client into `public_html/assets/vendor/`.

Everything after that is in [docs/README.md](docs/README.md): how the code hangs together, the flow of a sale, and the step by step for each payment gateway (what to create on the BTCPay, Stripe and PayPal side, which webhook events, where the ids go).

## Payments in one paragraph

Checkout writes a `pending` order and sends the customer to `/cart/payment/<order>`, which shows a button for every gateway that is switched on. The button creates the invoice on the gateway's side and sends the customer there. When they pay, the gateway's webhook flips the order to `paid` and the emails go out. When they come back to the payment page the store also asks the gateway directly, so a lost webhook never strands an order. Card numbers never touch your server, Stripe and PayPal host their own pages, and Bitcoin goes straight to the wallet connected to your BTCPay Server.

## Scripts

- `push.sh` commits and pushes this folder to GitHub. It refuses to push a tracked `config.php` or a database dump with account rows in it.
- `deploy.sh` rsyncs `public_html` to a server over one SSH connection, keeps the product images uploaded on the server, and fixes the folder permissions. Change `NUC` and `DEST` at the top for your box.
- `update-libs.sh` refreshes the vendored libraries from their newest GitHub release and writes a `VERSION` file next to each.

## Layout

    public_html/
      index.php, routes.php, autoloader.php   every url to a controller, classes found without composer
      controllers/      page controllers render views, controllers/api/ answer json
      models/           one class per table
      libraries/        cart, mail, sessions, database, and PaymentLibrary with the three gateways under it
      views/            plain php templates, shared pieces in views/include/
      assets/js/        FlightJS: routes.js maps a url to a page class in pages/
      assets/vendor/    third party code, picked up by the same autoloader
      flight/           the Flight PHP framework
      config/           config.example.php, errors.php, security.php
    docs/               the longer read
    ib_store.sql        the database

## Adding a payment gateway

One class in `libraries/` extending `PaymentLibrary` with `enabled()`, `start()`, `check()` and `webhook()`, one line in `PaymentLibrary::all()`, a block of defines in `config.php` and an icon in `views/cart/payment.html`. The controller, the payment page and the emails do not need to know it exists.
