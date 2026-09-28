# ADR-0054: Studio booking invoices and payment records

Date: 2026-09-27. Status: accepted for implementation.

Studio service invoices live in `app/Features/StudioBilling`, separate from retail media orders/entitlements and crew invoices. One active invoice per booking can contain multiple service lines. A void invoice remains auditable and permits a replacement. Photography minimum spend does not automatically become a studio charge.

A draft is based on the current approved booking and, for videography, confirmed unique family numbers. Issuing checks the source fingerprint and freezes issuer, recipient, service lines and GST-inclusive amounts. Subsequent booking edits do not change an issued invoice. Invoice URLs use public UUIDs; the displayed number is the database sequence plus 100000, so numbers increase but may have gaps.

Only staff with existing admin access manage invoices. Studio viewing, payment and PDF endpoints require current Studio Hub access to the exact studio. A retail gallery entitlement is insufficient. Drafts are staff-only. PDFs are generated on an authorised request with remote resource loading disabled, escaped fields, local branding and an authenticated Hub link/QR.

Online payments use a distinct Braintree integration rather than the retail fake gateway. The server reserves a durable attempt under an invoice lock before any network call, calculates the outstanding balance itself and checks a unique request key. Card fields and 3DS are hosted by Braintree; only a one-use nonce reaches Laravel and it is not stored. Browser success alone is not proof of payment. Amount, AUD currency and attempt reference must match the transaction. Only settled transactions reduce the balance; pending and uncertain results block further charges. Status checks and scheduled reconciliation never submit new sales.

Manual received payments permit partial balances and require a date and reference. Correcting a manual entry records a reversal with a reason; it does not refund money. Gateway refunds and credit notes need a separate future workflow. Test invoices and sandbox/simulated payments cannot settle live invoices. Local fake mode is refused outside local/testing environments.

No card credentials or live transactions were needed to implement or test this feature. Production activation requires configured gateway credentials/AUD merchant account, sandbox end-to-end verification, HTTPS and the application scheduler. Settlement polling is every 15 minutes; uncertain transactions with no unique provider match remain blocked for staff investigation. This feature does not implement the separate director video-review/payment gate.
