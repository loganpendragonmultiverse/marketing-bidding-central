# Development

## Scope

Marketing Bidding Central 1.0.0 is a self-contained Laravel 13 / PHP 8.3 application. Preserve the ranking, payment confirmation, owner access, metadata network safety, moderation and privacy boundaries. Runtime state belongs to each operator. Keep deployment secrets and real customer/payment data out of Git and release assets.

## Commands

Install the Composer lock file, run vendor/bin/pint, php artisan test, composer validate --strict --no-check-version, composer audit, PHP syntax checks and scripts/audit-release.py. Package with scripts/package-release.py; publish assets from the exact tagged commit after CI passes. No frontend toolchain is required.

## Boundaries

- No central branding, hosted feedback, contact relay, analytics IDs, production settings or private data.
- Do not open payment gates by default, invent transactions, or infer payment confirmation from browser navigation.
- Preserve signed Stripe verification, exact amount checks, idempotent ledger confirmation and owner-only top-ups.
- Preserve URL normalization, DNS/network checks, pinned requests, TLS and bounded metadata reads.
- Do not add public default admin credentials or customer passwords.
- Legal templates are intentional placeholders for the operator. The MIT license stays complete.
- Contact data and local mail logs are private; template rendering escapes user text.
- Public document root must remain public/. Never republish vendor/, .env, storage, live databases or installation history.

## Release

Keep VERSION, composer.json, marketplace runtime version, README download links and CHANGELOG consistent. Run isolated tests, lint, audit and package checks, then use a reviewed PR, exact merged commit and annotated version tag. CI packages only tracked source. Verify published SHA-256 and ZIP contents.

CodeQL covers JavaScript; it does not provide PHP analysis. PHP correctness is verified with syntax checks, framework integration tests and dependency audit. Test doubles do not certify live Stripe, SMTP or host configuration.
