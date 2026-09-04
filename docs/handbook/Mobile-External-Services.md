# Mobile external services

The mobile API is complete without embedding provider credentials in source
control. Production activation still requires the following private deployment
configuration.

## Address search

Set `GOOGLE_MAPS_SERVER_KEY` to a server-side Google Places API key. Restrict the
key to the required API and production server egress addresses. It must never be
included in the Flutter application. Address queries are authenticated,
rate-limited, restricted to Australia and cached by the Laravel API.

## Password reset mail

Configure a real Laravel mail transport and sender domain. Verify SPF, DKIM and
DMARC, then test both known and unknown addresses. The public API deliberately
returns the same result for both cases.

## Push notifications

Configure `MOBILE_PUSH_GATEWAY_URL` and `MOBILE_PUSH_GATEWAY_TOKEN` as described
in [Mobile push gateway](../mobile-push-gateway.md). Run supervised Laravel queue
workers. Provider credentials remain in the gateway, not this application or
the Flutter client.

## Upload malware scanning

Run ClamAV's `clamd` service on a private application network and configure
`UPLOAD_MALWARE_SCAN_ENABLED=true`, its host and port. Files are streamed to
clamd before private storage. Detected files receive a validation error;
unavailable or indeterminate scanning fails closed and prevents storage.

Keep ClamAV signatures current and monitor both the service and update process.
Use the harmless EICAR test signature only in an isolated, authorised launch
test. Never upload it to a live customer workflow.

## Required launch validation

Run `sail artisan production:validate` with production configuration. This now
rejects missing address-search, push, public support/privacy and malware-scan
settings in addition to the existing database, queue, mail, storage and security
checks. Provider delivery and infrastructure encryption still require live
smoke tests and cannot be proven from source code.
