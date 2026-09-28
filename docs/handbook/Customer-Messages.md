# My Messages

The customer menu order is Studio Hub (where authorised), My Media, My Messages,
and My Profile. Studio Hub retains its distinct appearance. My Messages uses the
same account heading, card styling, sticky sub-navigation and tab scroll retention.

- **Notifications:** automated order/media notices, gallery announcements and
  complimentary-media updates. Mark as read clears that entry's unread badge.
  An emailed update has one entry with its delivery information.
- **Conversations:** personal account emails and support threads, newest activity
  first. Opening a thread marks only that conversation as read. Customers can
  reply and attach a JPG, PNG, WebP, PDF or TXT file up to 10 MB.
- **New message:** Subject and Message are visible by default. A collapsed
  **Advanced options** section above them contains Topic, Event, Order, Expired
  media and Attachment. It opens automatically if one of those fields has a
  validation error. Download reactivation continues to validate the specific expired
  entitlement and retain the audited approval flow.

Unread notifications and unread conversations have separate tab counts. Their
sum appears on the My Messages menu. Listing a tab does not mark everything read.

Administrators open a thread from Customer support or the customer account's
support history. New messages/replies appear in Pending and its parent menu
badges. Sending a reply marks the thread answered and queues a customer email.
The existing reactivation decision form remains available in Customer support.
Help remains the FAQ page; Contact us opens New message. Old reactivation links
forward their pre-filled context to the new form.

After deploying the migration, run `sail artisan customers:sync-inbox` once to
import existing correspondence. It can be rerun safely and sends no emails.
Subsequent correspondence is projected automatically. Existing mail preferences,
queue configuration and local email rehearsal rules continue to apply.

Ordinary email-app replies are not yet imported. Vodien/cPanel is confirmed;
the connection details and remaining activation steps are in
[Email mailbox routing](Email-Mailbox-Routing.md). Until that connection is configured, the email action
opens the conversation so the customer can reply in DancePro. Studio-specific
booking, invoice and production discussions continue in Studio Hub.
