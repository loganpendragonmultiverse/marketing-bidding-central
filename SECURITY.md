# Security

Report vulnerabilities privately through this repository's Security > Report a vulnerability. Do not publish credentials, customer details, private management links, payment payloads or exploit data in public issues.

The latest published release is 1.1.0. Use the latest published patch and review security notices. This is an as-is source distribution under the MIT License; future maintenance and features are discretionary. Its Forge Gold classification describes the reviewed distribution scope and does not certify an operator's deployment or live providers.

Sensitive boundaries: Stripe signing secrets, APP_KEY and encrypted fields, administrator password hash, customer email codes, bearer management tokens, server-side URL fetching, private contact messages and release artifact contents. Keep public/ as the sole web root and payment gates closed until the operator is ready.

A source audit or passing test suite is not a security guarantee. Apply your own HTTPS, database/email controls, log access and recovery procedures.
