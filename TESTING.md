# Testing

Use PHP 8.3+, Composer 2 and Python 3.10+. SQLite tests use an in-memory database, test-only encryption key, array session/cache and fake mail/payment providers. No real credentials, charges, refunds or outbound customer messages are needed.

```sh
composer install
composer validate --strict --no-check-version
vendor/bin/pint --test
find app bootstrap config database routes tests public -name '*.php' -exec php -l {} \;
php artisan test
composer audit
python3 scripts/audit-release.py
python3 scripts/package-release.py
```

Integration acceptance covers public/protected routes, independent contact intake and escaping, all closed gates, owner-only access, first-payment publication, cumulative credit/debit, idempotency, mismatched payment amount, ranking ties and moves, email-code expiry/reuse, category/report/admin operations, safe destination normalization and private-network rejection.

A fresh install must follow INSTALLATION.md from a release archive, with empty local data. Test config/route/view caches and the web server public/ boundary. Render homepage, listing/inline details, submit, customer login, legal pages and administrator sections at desktop and phone widths. Check overflow, runtime errors and accidental third-party network calls.

Before live operation, the operator must exercise its own SMTP delivery, HTTPS setup, MySQL concurrency and Stripe test-mode checkout/webhooks/refunds. Automated mocks and source checks are not provider acceptance. No live payment, refund or customer email is part of the release tests.
