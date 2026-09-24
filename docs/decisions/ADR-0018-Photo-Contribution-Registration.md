# ADR-0018: Photo contribution registration

Date: 2026-09-21
Status: Implemented registration foundation; transfer and release remain pending

Register one photo contribution per immutable upload plan, active item UUID,
crew profile and crew role. The upload plan resolves the scheduling event and
approved revision. Keep item identities scoped to their plan as in ADR-0017.
A public contribution UUID will identify the photographer/item source of later
file reservations. P1 and P2 remain distinct even for the same programme item.

The API requires administrator policy and the existing concert-media:upload
write ability. Scope the plan through the route event's programme and require the
latest approved revision. Recheck active published photographer assignments on
every request, including retries. Competition items additionally require an
assignment on their programme day. Do not treat a cached Mac selection as upload
authority. Validate every item before inserting anything; a programme row lock
and compound unique constraint make retries idempotent. Retired aliases, breaks,
groups and foreign items cannot receive new contributions.

Persist the qualifying assignment UUIDs at first registration for provenance.
These are historical identifiers, not a perpetual permission grant. No cached
assignment is used to bypass the live roster check on retry. All new records have
status awaiting_files; request data cannot set status or mark receipt, verification,
completion or release. No media assets, storage keys or customer galleries are
created by registration. Do not infer that absent contribution rows mean missing
photographers are waived: expected coverage still comes from Crew Management.

On explicit Sync Programme, the Mac scans its current queue, syncs the programme,
then registers distinct queued item IDs when a photographer has been selected.
Persist validated contribution links in the existing sync receipt, after checking
server/event/plan/person/role/item identity. Old receipts remain readable. If
registration fails, programme sync still succeeds and its receipt is retained;
explain the registration failure and allow an idempotent retry. No initial upload
starts automatically, and selecting a role does not gate preparation.

Before file transfer: add per-file reservation, content verification, event/gallery
mapping and a deliberate resolution for conflicting plans across laptops. Expected
contributions and exceptions need section/day/shift coverage rules; current day
matching alone is not a release decision. Reauthorize current roster and plan at
transfer time. Uploaded/purchased media must never move because a new plan was
registered. The completion gate must verify every expected contribution and must
not publish concert galleries automatically.
