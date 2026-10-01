# Server installation

## Requirements

PHP 8.3 or later, Composer 2, Nginx or Apache with PHP-FPM, HTTPS, and SQLite or MySQL. Enable ctype, curl, DOM, fileinfo, filter, hash, mbstring, OpenSSL, PCRE, PDO, session, tokenizer and XML; enable pdo_sqlite or pdo_mysql for the selected database. Ensure CLI and web PHP use the same compatible version and extensions.

The deployment layout follows [Laravel's deployment guidance](https://laravel.com/framework/docs/13.x/deployment). CSS/JS are already included. No Node, hosted platform or proprietary network integration is required.

## Install a release

Extract the release ZIP outside the public document root, or clone the repository and check out v1.0.0. Verify the published SHA256SUMS before extracting.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit .env privately. Set APP_ENV=production, APP_DEBUG=false, APP_URL to your HTTPS origin, SESSION_SECURE_COOKIE=true, and a valid MAIL_FROM_ADDRESS for your domain. Keep APP_KEY secret and stable; rotating it without migrating encrypted fields makes old private fields unreadable.

For local SQLite, create database/database.sqlite. For production MySQL, create a dedicated database and user; set DB_CONNECTION=mysql and DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD. Grant only the application's database permissions.

```sh
php artisan migrate --seed --force
php artisan storage:link
php artisan marketplace:admin
```

The admin command asks for the operator email and password with hidden password input and stores only a password hash in .env. It does not create a default login. Re-run config:cache after changing configuration. The initial seed adds categories and closed market settings only; it contains no example listings, users or transactions. Do not re-run the initial seed on an active installation: it intentionally closes the market settings.

## Web server and permissions

Set the document root to the release's **public/** directory. The supplied deploy/nginx.conf is a starting point: change the hostname, path and PHP-FPM socket, then configure trusted TLS and HTTP-to-HTTPS redirection. Apache uses public/.htaccess with mod_rewrite and AllowOverride enabled.

Keep the release source and .env outside the document root. Make storage/, bootstrap/cache/ and the chosen SQLite database writable by the PHP process; keep source read-only and .env readable only by the deployment/PHP accounts. Do not use world-writable permissions.

```sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Check /up, homepage, categories, /submit, /contact and the protected admin/customer routes. Requests for /.env, /composer.json, /storage/logs/laravel.log and /_app/.env must not reveal files.

## Email and customer access

Configure MAIL_MAILER=smtp plus your own MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD and MAIL_SCHEME according to [Laravel mail configuration](https://laravel.com/framework/docs/13.x/mail) and your provider. The default log transport is for local review, so it does not send email. Protect local logs containing development login messages. QUEUE_CONNECTION=sync needs no worker; if you change transports or queue behavior, validate delivery explicitly.

The first paid listing assigns its private contact email to a customer account and sends a customer-login link. Existing customers request a six-digit, single-use, ten-minute code. Never add a public default customer password.

## Policies and payments

Replace all files under resources/views/legal/ with your own terms, privacy notice, payment/refund policy, listing policy and disclaimers. Set MARKETPLACE_LEGAL_UPDATED_AT to your revision date and MARKETPLACE_LEGAL_READY=true only after completing those pages.

Use your own Stripe account and test credentials first. Set STRIPE_SECRET and STRIPE_WEBHOOK_SECRET privately. Configure a signed webhook at https://your-host/webhooks/stripe for checkout.session.completed, checkout.session.expired and payment_intent.payment_failed. The code uses hosted card Checkout. The webhook route is CSRF-exempt and still requires a valid Stripe signature; see [Stripe webhook guidance](https://docs.stripe.com/webhooks).

Open both payment gates only after verifying your installation:
1. Set MARKETPLACE_OPEN=true in .env, then refresh config cache.
2. In /admin/system, enable the database marketplace setting with a reason.
3. Confirm your policies are complete, SMTP works, and both Stripe credentials are present.
4. Test a first payment, duplicate signed delivery, owner-only top-up, failed/expired checkout and an admin refund in Stripe test mode. Confirm the amount and ledger agree.
5. Switch to your own live credentials and matching live webhook only after operator acceptance.

A browser success redirect never confirms payment. Only verified provider events can publish a new paid listing.

## Backups and updates

Back up your database, private/public uploads and APP_KEY securely. Store backups outside the document root and test restoration on an isolated server. Do not commit backups or .env.

For updates: close the payment gates, enter maintenance mode, back up your data/key, replace source, run composer install --no-dev --optimize-autoloader and php artisan migrate --force, refresh caches, run smoke checks, then leave maintenance mode and reopen deliberately. Keep the previous release for rollback; reverse schema changes only with a tested database restore.

## Uninstall

Close the market, disable its webhook destination, stop any application-specific workers, remove the virtual host and application directory, then remove only its dedicated database/user and stored data after accounting for required records. Never remove a shared database or unrelated Stripe resources.
