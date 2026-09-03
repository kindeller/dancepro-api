# Mobile push gateway

DancePro queues push delivery whenever a crew notification is created. Device
tokens are encrypted in the database and are sent only to the configured push
gateway.

Configure `MOBILE_PUSH_GATEWAY_URL` and `MOBILE_PUSH_GATEWAY_TOKEN` outside
source control. The gateway must accept a bearer-authenticated JSON request
containing platform, environment, push token, generic title/body, and a
structured destination. A `404` or `410` response disables the registration.

Push previews intentionally contain no chat, contract, financial, medical, or
other private notification content. Queue workers are required in production.
The gateway owns the Apple APNs key, Firebase credentials, retry policy, and
provider-specific delivery logging.
