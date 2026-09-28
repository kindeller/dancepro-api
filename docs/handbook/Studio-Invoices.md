# Studio invoices

## Staff workflow

1. Open an approved **Event booking** linked to a studio. Under **Studio invoice**, choose **Confirmed video quote** or **Custom invoice**, then **Create draft invoice**. Video invoices require confirmed final family numbers. Prices include GST: $1,500 per concert or $15 per unique family, whichever is greater.
2. Review the lines, quantities, prices and due date. The default due date is seven days after the last active event. Drafts are private. Custom lines can cover agreed adjustments; never invoice a photography shortfall without the separate minimum-spend/resolution review.
3. **Invoice settings** contains the business name, ABN, optional address and bank details. The local installation was initialised from the supplied Square example. Verify these details before real use. Saving settings only affects subsequently issued invoices.
4. **Issue invoice to Studio Hub** makes the invoice visible to authorised studio contacts. Issuing does not automatically email anyone. Use the existing reviewed communication workflow to notify them; no invoice PDF is automatically attached to those messages.
5. Use **Sales & reports > Studio invoices** for balances/history and **Download PDF**. **Record received payment** records money already received by bank transfer or elsewhere, including partial payments. It does not charge the studio.
6. Void only an unpaid invoice with no pending payment. Paid invoice corrections need the future credit-note/refund workflow. A mistaken manual receipt can be reversed with a reason; this is only a ledger correction.

One active invoice per booking is supported. Repeated create clicks reopen it. Include related services as additional lines rather than creating duplicate invoices. Voided invoices remain visible in history. Sequential display numbers can have gaps and are distinct from retail order numbers.

## Studio workflow

**Studio Hub > Invoices** lists issued invoices and payment status. Studios can view service dates, line items, included GST, balance, transfer instructions, payment history and download a branded PDF. A QR and link in the PDF open that exact invoice after studio sign-in. There is also an Invoices link on the current Event timeline.

Live card checkout uses Braintree Hosted Fields and 3DS2. The outstanding amount comes from the server. Accepted payments can remain **Payment pending** until settlement; **Check payment status** and scheduled reconciliation update it. Do not pay again while pending. An uncertain result is deliberately held until verified by the provider. A payment pending with no matching transaction needs staff/provider investigation; it must not be casually reset to permit another charge.

The tracker derives **Invoice** from issuance and **Payment received** from the settled/recorded balance, with the actual invoice due date for payment follow-up. Email delivery or manually changing an old tracker task cannot invent a receipt. The timeline exposes those same statuses. Tracker task buttons reflect their destination: Create invoice from booking when no invoice exists, Open draft invoice for a draft, and Open studio invoice once issued. Bookings awaiting approval or a studio association offer Review booking instead.

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
