# Studio invoices

## Staff workflow

1. Open an approved **Event booking** linked to a studio. Open the **4.2 Invoice & payment** booking tab, choose **Confirmed video quote** or **Custom invoice**, then **Create draft invoice**. Video invoices require confirmed final family numbers. Prices include GST: $1,500 per concert or $15 per unique family, whichever is greater.
2. Review the draft lines, quantities, prices, discount and notes in that same tab. The invoice email starts a 14-day payment deadline; the tracker asks for it to be sent seven days before the final event. Drafts are private. Custom lines can cover agreed adjustments; never invoice a photography shortfall without the separate minimum-spend/resolution review.
3. **Invoice settings** contains the business name, ABN, optional address and bank details. The local installation was initialised from the supplied Square example. Verify these details before real use. Saving settings only affects subsequently issued invoices.
4. **Send invoice** saves the draft, makes it visible to authorised studio contacts, and opens the invoice email template. Review and send the email to start the payment deadline. The email links to the Studio Hub; no invoice PDF is automatically attached.
5. The **4.2 Invoice & payment** tab also contains **Download PDF**, payment details and invoice history. **Sales & reports > Studio invoices** links to the same booking workspace. **Record received payment** records money already received by bank transfer or elsewhere, including partial payments. It does not charge the studio.
6. Void only an unpaid invoice with no pending payment. Paid invoice corrections need the future credit-note/refund workflow. A mistaken manual receipt can be reversed with a reason; this is only a ledger correction.

One active invoice per booking is supported. Repeated create clicks reopen it. Include related services as additional lines rather than creating duplicate invoices. Voided invoices remain visible in history. Sequential display numbers can have gaps and are distinct from retail order numbers.

## Studio workflow

**Studio Hub > Invoices** lists issued invoices and payment status. Studios can view service dates, line items, included GST, balance, transfer instructions, payment history and download a branded PDF. A QR and link in the PDF open that exact invoice after studio sign-in. There is also an Invoices link on the current Event timeline.

Live card checkout uses Braintree Hosted Fields and 3DS2. The outstanding amount comes from the server. Accepted payments can remain **Payment pending** until settlement; **Check payment status** and scheduled reconciliation update it. Do not pay again while pending. An uncertain result is deliberately held until verified by the provider. A payment pending with no matching transaction needs staff/provider investigation; it must not be casually reset to permit another charge.

The tracker combines financial state in **4.2 Invoice & payment**: Pending until final family numbers are confirmed, Not invoiced until the first invoice email is sent, Not paid while a balance remains, and Paid once settled. The first email starts the 14-day payment deadline; reminders do not reset it. Manual tracker changes cannot invent a receipt.

The tracker cell, invoice list and old invoice URLs all open the booking invoice tab. It shows creation requirements when no active invoice exists, the editor for a draft, and the invoice plus payment details once issued. Old voided invoices remain accessible through **Other invoices for this booking**; selecting one cannot expose another booking's invoice. Opening the tab does not create an invoice. The tracker bar and its Back to concert tracker / Back to event controls stay inside the tab.

## Setup and boundaries

- `STUDIO_PAYMENT_DRIVER`: `disabled`, `fake` (local/testing only), or `braintree`.
- Configure Braintree environment, merchant ID, public/private keys and `BRAINTREE_AUD_MERCHANT_ACCOUNT_ID` using the approved secret provisioning workflow. Do not put values into source control or support messages.
- Braintree sandbox/fake works only with invoices marked Test. A live invoice cannot be paid with simulated funds. Local default is fake; production default is disabled.
- `sail artisan studio-invoices:reconcile` checks outstanding Braintree attempts. The application scheduler runs it every 15 minutes with overlap prevention. This command never initiates a charge. The task is a no-op when Braintree is not configured. No scheduler or general queue worker was started for this change.
- Sandbox and live gateway configuration and real card/3DS end-to-end verification remain outstanding. Automated tests mock gateway responses and exercise local simulation; they are not proof that the merchant account is ready.
- Customer-initiated partial card payments, gateway refunds/credit notes, accounting export and email PDF attachments are not included. Manual partial receipts are supported.
- The final-concert director review/payment gate remains the separate next delivery-workflow feature. Issued invoice changes do not yet automatically notify studios or replace old scheduled messages.

## References

- [Braintree sale](https://developer.paypal.com/braintree/docs/reference/request/transaction/sale/php)
- [Braintree 3DS2 integration](https://developer.paypal.com/braintree/docs/guides/3d-secure/step-by-step-integration/javascript/v3/)
- [Studio invoice architecture](../decisions/ADR-0054-Studio-Booking-Invoices.md)

## Verification (27 September 2026)

- Full Sail suite: 829 tests, 7,691 assertions, passed. Coverage includes studio ownership/revocation, draft privacy, source changes, fixed issued amounts, PDF responses, simulated versus live boundaries, replay protection, partial receipts/reversal, pending/unknown payments, settlement validation and tracker linkage.
- Scoped Sail Pint, JavaScript syntax check, complete route listing (603 routes) and `git diff --check` passed.
- New migration applied locally; rollback reviewed in child-to-parent order. It only removes the new billing tables.
- Rendered admin, customer and draft screens at 1440px and 390px with no page overflow. Draft add/remove controls worked. Single-page and three-page PDFs were rendered and inspected; the invoice link and QR are generated from the authorised Hub route.
- No real card charge, customer invoice issuance or outbound customer email was performed. PDF/UI review records were rolled back. Braintree sandbox/live credentials and an actual 3DS payment remain an external activation check.

The Studio Hub **Invoices** tab uses the same header and navigation as Events and
Messages. **Quotes & minimum spends** appears above issued invoices, with a
separate expandable summary for each saved, non-cancelled booking. These are the
same current quote cards shown on the Event timeline: cancelled events are
excluded and confirmed family numbers replace estimates. They do not alter any
issued invoice. Drafts stay private. Overdue, unpaid issued invoices show their
due date in red; PDF and payment actions remain on the invoice.

The invoice list and invoice detail retain the three Studio Hub summary links:
Events, actions remaining and Messages. Actions remaining opens the Events page
with unfinished events expanded. Reading an invoice does not mark messages read.
Studio Hub and My Profile submenu tabs preserve scroll position when switching.
