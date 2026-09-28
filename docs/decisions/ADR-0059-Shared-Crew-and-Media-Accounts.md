# ADR-0059: Shared crew and media accounts

Date: 2026-09-28
Status: Accepted

## Context

Staff may also be dancers or studio contacts. Separate customer records for the same person would split sign-ins, purchases and studio access. The web interfaces need navigation between roles while the crew and customer mobile apps remain separate.

## Decision

Use the existing User record for all roles. Active customer, crew and staff accounts can enter Media. Crew Hub requires an existing CrewProfile, Admin requires is_admin, and Studio Hub requires studio-specific active StudioAccountAccess. The legacy user type continues to provide the default home destination; it is not an exclusive role selector.

An admin-only operation adds a crew profile to an existing active account under a user-row lock. It preserves user credentials, type, customer records and other permissions, rejects duplicate crew profiles, and does not send an invitation. Crew profile edits preserve the existing user's type. Crew onboarding applies to non-admin crew-profile holders regardless of legacy type. Media email verification applies to all non-admin users.

Existing gallery registration, media ownership and app token scopes remain the authorization boundaries for content. Customer-only merge and anonymisation tools reject crew-linked and Admin accounts rather than disconnecting employment records.

## Consequences

A customer can become a crew member without creating a second sign-in. Role links appear only for the hubs they can access. Studio ownership remains independent of employment. No new role enum or mobile-app navigation is introduced.
