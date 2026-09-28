# Admin navigation notifications

Red badges identify work needing attention. Counts appear at the actionable
queue and roll up to its parent menu, including when the parent is collapsed.
Zero counts are hidden. Counts refresh on the next page load; there is no
background polling.

| Queue | What counts | Where the badge appears |
| --- | --- | --- |
| Studio messages | Incoming studio messages not yet read by staff | Studio messages |
| Customer support | Requests with Pending status | Pending tab, Customer support, Customers |
| Customer follow-ups | Incomplete notes with a follow-up time at or before now | Due now tab, Customer follow-ups, Customers |
| Pending events | Uncancelled, unscheduled booking items awaiting approval, belonging to a Pending or Approved booking | Pending events tab, Event management, Hub management |
| Exceptions | Exceptions marked as requiring action | Exceptions, Hub management |
| My Hub | The signed-in crew member's existing red shift actions: availability responses, assignment acknowledgements and cover requests | My Hub |

Customers adds support and follow-up counts. Hub management adds pending events
and action exceptions. Each leaf queue contributes once; an intermediate menu
total is not added again. These counts measure outstanding actions, not unique
customers or studios. Studio messages counts messages rather than conversations.

Pending events includes all years, matching its queue. The Concert tracker badge
remains separate and follows its selected year (current year elsewhere in admin).
My Hub does not include its yellow timesheet/payment or blue chat indicators.

The shared `AdminNavigationIndicators` service calculates the menu and tab values
once per request and respects admin/scheduling access. Pending events shares its
query scope with the queue and tracker; due follow-ups shares the queue query.
Reading messages, resolving support, completing notes or approving/cancelling
events removes the applicable badges after navigation or refresh.

Potential future additions are payment issues, overdue studio invoices and crew
payments awaiting action. Any crew-payment roll-up must avoid counting the same
overdue invoice again when it already appears in Exceptions.
