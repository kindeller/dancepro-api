# Staff/Admin and Crew Access Boundaries

## Decision

Administrative web routes, competition object listings, and download-link management are restricted to active users with the `is_admin` permission.

The shared rule lives on `User::canAccessAdmin()`. The `/admin` web route group enforces it through `admin.required` middleware, while API resources continue to enforce it through gates and policies. Existing `staff` and `admin` user records are granted the permission when the column is introduced.

Crew web routes (My Hub) are restricted to active, non-admin crew members with a crew profile. Administrators use the Admin system rather than personal My Hub timesheet and invoice workflows. The shared rule lives on `User::canAccessCrew()` and is enforced on the whole `/crew` route group through `crew.required` middleware. Onboarding checks run after this identity boundary, and assignment-, event-, conversation-, invoice-, and notification-specific ownership checks remain in place.

Admin access is managed through the **Admin access** checkbox on the administrator's crew edit page. Granting it does not delete the person's crew record, but it changes their permitted interface from My Hub to the full Admin and media system.

Public token-based download delivery remains unchanged. A recipient does not need an account to use a valid tracking link.

## Reason

Authentication alone does not distinguish administrators, crew, and customer accounts. Explicit route boundaries prevent one portal from being entered by another account type, while resource policies and ownership checks provide defence in depth. The media delivery workflow remains separate from administrative access.
