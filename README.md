# Marketing Bidding Central

Self-hosted cumulative-bid marketing placement and project discovery.

A project begins as a private submission and becomes public after its first confirmed payment. Rankings use cumulative confirmed value; owner top-ups can move a listing up, and recorded refunds can move it down. An administrator can also create listings and make clearly labeled ledger adjustments.

## Features

- Overall and category rankings, tie ordering, recent activity, search and shareable inline listing details.
- Project submissions with website or social-profile destinations, social-handle normalization and bounded metadata inspection.
- Owner-only paid top-ups, private management links, passwordless email-code customer access and listing editing.
- Stripe card Checkout, signed webhook verification, retry receipts, duplicate-payment protection and cumulative bid ledgers.
- Owner administration for listings, categories, payments/refunds, reports, category requests, system settings and a private contact inbox.
- Aggregate listing-view and outbound-click counts, protected private email fields, CSRF protection and rate limits.
- Operator-editable terms, privacy, payment/refund and listing-policy templates.
- Standard Laravel `public/` web root, installation guide and Nginx/Apache examples.

## Quick start

Requires PHP 8.3+, Composer 2, SQLite or MySQL, and the PHP extensions listed in [INSTALLATION.md](INSTALLATION.md). No Node build is needed; CSS and JavaScript are included.

```sh
git clone https://github.com/loganpendragonmultiverse/marketing-bidding-central.git
cd marketing-bidding-central
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve --host=127.0.0.1
```

Open http://127.0.0.1:8000. Payments are disabled. Configure an administrator with `php artisan marketplace:admin`, then sign in at `/admin/login`.

For a production server, follow [INSTALLATION.md](INSTALLATION.md) and [the operator guide](docs/OPERATOR-GUIDE.md). Point the server at **public/**; never expose the project root. The built-in development server is for local review only.

## Download

[Marketing Bidding Central 1.0.0 release](https://github.com/loganpendragonmultiverse/marketing-bidding-central/releases/tag/v1.0.0) contains a source ZIP and SHA256SUMS. Install locked dependencies with Composer. Runtime data, accounts, secrets and dependencies are not bundled.

## Privacy and safety

This distribution has no analytics tag, remote feedback widget, centralized contact relay, account credentials or operator data. Contact messages stay in the installation's encrypted private inbox. No central service is needed.

The application contacts submitted public destinations for metadata, loads submitted public images in visitors' browsers, redirects outbound clicks, and contacts the operator's configured Stripe/email providers. Browser sessions, listing/payment records and aggregated metrics are stored on the operator's server. Private contact details are encrypted with APP_KEY; back up that key securely alongside the database.

All marketplace payment gates start closed. Replace the legal templates, configure your own email and Stripe credentials, test in Stripe test mode, and deliberately open the gates before accepting payments.

## Limitations

- This is a self-hosted application, not a hosted service, payment processor or turnkey legal agreement.
- USD is the supported currency. Payment-provider fees, taxes, disputes and accounting remain operator responsibilities.
- Rankings are paid placement, not endorsements or guarantees of traffic or conversions.
- Metadata inspection rejects private/reserved network destinations, pins resolved IPs, verifies TLS and limits redirects, bytes and time. Some valid websites block automated requests; users can still supply their own description.
- Private management links are reusable bearer credentials until expiry; protect them. Email codes are single-use and expire after ten minutes. SMTP must work for customer access.
- SQLite is available for local review and small installations; MySQL is recommended for concurrent public operation. Test your database, email delivery and Stripe test events before live use.
- The release uses isolated integration tests with synthetic payment events. No real charge, refund, production customer email or live provider acceptance is claimed.
- Legal pages are intentionally incomplete operator templates. The MIT software license is separate and remains applicable.
- Contact messages require the administrator to check the inbox; no outbound contact notification or reply sender is included.

## Development

```sh
composer install
composer validate --strict --no-check-version
vendor/bin/pint --test
php artisan test
composer audit
python3 scripts/audit-release.py
python3 scripts/package-release.py
```

See [DEVELOPMENT.md](DEVELOPMENT.md), [TESTING.md](TESTING.md), [CHANGELOG.md](CHANGELOG.md), [SECURITY.md](SECURITY.md) and [CONTRIBUTING.md](CONTRIBUTING.md).

MIT licensed; dependency notices are in [NOTICE.md](NOTICE.md).
