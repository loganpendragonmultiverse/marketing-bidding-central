# Using Marketing Bidding Central

## Visitors and project owners

Browse overall rankings or a category. Select a project to open inline details; the URL remains shareable, and direct listing pages work without JavaScript. Outbound links use the tracked redirect. Merely displaying a ranking row does not count as a listing view.

Submit a project with a public URL, bare domain or supported platform/handle, category, description, private contact email and optional logo. A pending listing is not public. Save its private management link. When the market is configured and open, card Checkout begins; the signed confirmation publishes it.

Use Customer login to request an email code and manage owned listings. Only the owning customer or private management token can edit or add money. Active listing top-ups start at $1; first payments start at $5 by default. A confirmed $5 first bid and $3 top-up produce an $8 cumulative total.

## Administrator

Use /admin/login with your configured operator credentials. The dashboard links to focused sections:
- Listings: filter/search, create, edit and enforce listing status; inspect ledger and metrics.
- Payments: real transactions and bid history; eligible refunds reduce the ranking balance.
- Reports and category requests: review and resolve submissions.
- Categories: create/edit categories and active state.
- System: payment gates, minimum first bid, webhook receipts and audit trail.
- Contact messages: read private messages and mark resolved.

Manual balance adjustments have an admin_adjustment ledger type and never claim a Stripe charge. A reason is required for sensitive owner actions.

Rankings order active, positive-balance listings by cumulative value, then time the total was reached, then ID. Payment attempts do not increase rank. Keep monetary reconciliation and provider records; application counts are not accounting certification.

Contact replies are handled by the operator outside the application. No central feedback integration exists.

## Refund reconciliation

Administrative refunds record the provider reference and cumulative refunded amount. Pending provider refunds reserve the corresponding ranking balance. Review their eventual outcome in Stripe; a pending refund that fails or a refund/dispute performed directly in Stripe requires manual reconciliation with an audited balance adjustment. Keep the application database and provider history together when resolving a failed request. This release does not automatically reconcile external refunds or disputes.

## 1.1.0 - 2026-10-16

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
