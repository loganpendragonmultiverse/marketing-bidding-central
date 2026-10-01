# Security

Report vulnerabilities privately through this repository's Security > Report a vulnerability. Do not publish credentials, customer details, private management links, payment payloads or exploit data in public issues.

Only 1.0.x is currently released. Use the latest patch release and review security notices.

Sensitive boundaries: Stripe signing secrets, APP_KEY and encrypted fields, administrator password hash, customer email codes, bearer management tokens, server-side URL fetching, private contact messages and release artifact contents. Keep public/ as the sole web root and payment gates closed until the operator is ready.

A source audit or passing test suite is not a security guarantee. Apply your own HTTPS, database/email controls, log access and recovery procedures.
