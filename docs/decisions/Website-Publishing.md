# Website publishing and shared media navigation

Date: 28 September 2026

Home, About and Services use fixed routes and editable draft/published content
snapshots. Section cards are a bounded structured list, not arbitrary HTML or a
page-builder executable format. Administrators explicitly publish their saved
content; drafts cannot leak through the public routes. Existing authorised admin
access governs publishing.

Crew biographies use an independent approved public snapshot linked to the crew
profile. The original private biography remains the crew member's submission.
A recorded reviewed-source value detects later edits, and a source hash prevents
approval from a form that missed a newer crew edit. Private profile photographs,
contact details and employment records are not exposed by the public biography.

Public upcoming events reuse SchedulingEvent with a rolling six-calendar-month
window and an explicit persistent hide override. The initial opt-in policy was
replaced at the owner’s request on 28 September 2026. Competition cards retain
full start/finish dates and venues; concert events are deduplicated to studio logos. Website visibility does not alter gallery publication, booking approval or
crew schedules. Public and customer Help share CustomerHelpArticle; its existing
published fields are retained while a separate draft permits editorial review.
Public help searches do not create anonymous tracking records.

The existing public media layout becomes the common website layout. Role-specific
hub buttons move into a separate row rather than creating separate accounts or
sites. Existing studio links remain available at /studios when / becomes Home.
