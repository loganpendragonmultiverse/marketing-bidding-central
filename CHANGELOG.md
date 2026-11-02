# Changelog

## 1.1.0 - 2026-10-16

- Updated Symfony HttpFoundation from 7.4.17 to 7.4.19 for CVE-2026-108082.

Administrators can download a local ranking-ledger inspection CSV from Payments. Choose
inclusive UTC dates covering at most 366 calendar days. Rows follow creation timestamp and
ledger ID order, with a captured maximum ID and 500-row read batches. Pending and failed
entries remain visible with their recorded status; exported signed changes and balances are
stored ledger values, not recalculated payment totals.

Only the UTC creation timestamp, listing ID, public ledger reference, source, status, currency,
signed change in cents, prior balance in cents, and resulting balance in cents are exported.
Text cells beginning with whitespace/control characters or spreadsheet formula prefixes receive
an apostrophe. Numeric cents remain signed integers. No private notes, email, management links,
payment-provider IDs, or provider payloads are included.

This is an inspection export, not an accounting report. It does not contact providers, send mail,
perform payments or refunds, or write ledger records. Concurrent updates to existing rows can
appear during streaming; the export is not a transactional accounting snapshot.

## 1.0.0 - 2026-09-30

### Added

- Initial standalone self-hosted Marketing Bidding Central release.
- Cumulative-bid rankings, category discovery, listing submissions and inline detail navigation.
- Stripe Checkout and signed confirmation, owner top-ups, refund/adjustment ledgers and administration.
- Passwordless customer access, private listing-management links, reports and category requests.
- Independent encrypted contact inbox within the application's administration.
- Operator policy templates, closed launch gates, server installation guide and secure public/ layout.
- Automated integration tests, source sanitization checks, CI, frontend CodeQL and reproducible source release packaging.

### Changed

- Generalized application configuration, branding, mail defaults and legal content for independent operators.
- Removed hosted-network links, feedback widgets, contact relays, analytics/verification identities, hosting-specific files and runtime data.

- Corrected the active-listing top-up minimum and cumulative partial-refund limits, with provider retry idempotency and locked ledger updates.
