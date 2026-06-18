# NRS Lifecycle

Veridex keeps NRS/FIRS invoice workflows synchronous unless a future product decision moves validate, sign, or transmit to queues.

## Lifecycle

1. Draft invoices are created through `InvoiceService`, which calculates authoritative totals server-side.
2. Validation moves the invoice through `pending_validation` to either `validated` or `validation_failed`.
3. Signing moves the invoice through `pending_signing` to either `signed` or `sign_failed`, captures immutable seller/buyer/line/tax snapshots, and stores the IRN returned by NRS.
4. Transmission moves the invoice through `pending_transmit` to either `transmitted` or `transmit_failed`.
5. Confirmation and webhooks can move transmitted invoices toward the final NRS state.
6. Payment updates for fiscalized invoices call NRS before the local payment status is recorded.

## Idempotency And Recovery

NRS submissions are tracked with idempotency keys, request/response payloads, response timing, and status. Public NRS webhooks are processed by queued jobs and deduplicated by message plus IRN.

Operational commands:

- `php artisan nrs:poll-confirmations`
- `php artisan invoices:recover-stuck`
- `php artisan invoices:mark-overdue`
- `php artisan ops:queue-health`

## Artifacts

Official PDF/XML artifacts are downloaded only after an invoice has an IRN and is fiscalized. Artifacts are stored under local NRS artifact paths with SHA-256 hashes written through trusted service paths.

## Security

Webhook signature verification is enabled by default. Local/testing bypass must be explicit configuration, not the production default.

Raw NRS debug export is development-only. Debug files can contain taxpayer and invoice payload data and must not be committed, published, or shared outside controlled support workflows.

Any NRS, Mailtrap, or other credentials that were ever present in local `.env`, pasted into documentation, or committed historically must be rotated operationally.
