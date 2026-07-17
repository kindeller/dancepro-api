# DancePro V2 Concerts, Studios, Media and Customer Access Migration Specification

## Purpose

This document defines the proposed database structure and migration approach for expanding the DancePro Laravel V2 application into the following areas:

- Studios
- Concerts
- Competition and concert media collections
- Managed media assets
- Optional customer accounts
- Concert access tracking
- Optional saved concert access
- Future carts, orders and individual photo purchases
- Durable media location tracking

The immediate priority is to establish a stable database design that can be implemented and tested locally before the customer-facing behaviour is finalised.

The design must preserve all existing V2 production data and functionality.

---

# 1. Migration Safety Requirements

## 1.1 Existing production tables

Migrations affecting existing production tables must be additive and non-destructive.

Do not:

- Drop existing tables.
- Drop existing columns.
- Rename existing columns.
- Change an existing column to an incompatible type.
- Make an existing nullable column required.
- Replace existing storage fields.
- Remove existing indexes or foreign keys.
- Rewrite existing download-link behaviour.

New columns added to existing tables should normally be nullable unless a safe default exists.

Any data backfill must be performed separately from the schema migration.

## 1.2 New tables

The tables introduced by this specification do not yet exist in production.

During local development, these new tables may be revised, dropped or recreated while the model is being validated.

Once deployed to production, they should follow the same additive migration policy.

## 1.3 Soft deletion

Use soft deletion for business-domain records where historical retention is useful.

Use `softDeletes()` on:

- studios
- concerts
- media_collections
- media_assets
- customer_profiles
- concert_access_grants
- orders

Do not use soft deletion for append-only logs unless there is a demonstrated requirement.

## 1.4 Public identifiers

Use internal numeric primary keys for relationships.

Use UUIDs for externally exposed business records.

UUIDs should be generated automatically by the model or database layer.

Recommended UUID-bearing entities:

- studios
- concerts
- media_collections
- media_assets
- concert_access_grants
- orders

Public API routes should use UUID route binding rather than sequential numeric IDs.

---

# 2. Agreed Domain Boundaries

## 2.1 Database-managed business entities

The following are meaningful business entities and must be represented in the database:

- Users
- Customer profiles
- Studios
- Concerts
- Competitions
- Media collections
- Managed media assets
- Concert access grants
- Orders and order items

## 2.2 Object-storage-managed files

S3 remains the physical source of media files.

The application should not create a database row for every photo merely because the photo exists in S3.

A database media asset record should be created when a file requires stable identity or individual business management.

Examples include:

- Videos
- Purchased photos
- Favourited photos
- Featured photos
- Photos with custom metadata
- Files that must survive an S3 location change
- Files referenced by orders or customer history

## 2.3 Media collection rule

Every meaningful gallery, folder or media grouping should have a `media_collections` record.

Individual files within a collection may be:

- Storage-derived only
- Database-managed through `media_assets`
- A mixture of both

This permits large photo galleries without requiring millions of database rows.

---

# 3. High-Level Relationship Map

```text
users
  ├── customer_profile
  ├──< concerts.created_by_user_id
  ├──< concerts.updated_by_user_id
  ├──< concert_access_grants
  ├──< concert_accesses
  ├──< orders
  ├──< download_links.generated_by_user_id
  └──< download_links.revoked_by_user_id

studios
  └──< concerts

concerts
  ├── belongs to studio
  ├──< media_collections
  ├──< concert_access_grants
  ├──< concert_accesses
  └──< download_links

competitions
  └──< media_collections

media_collections
  ├── belongs to either concert or competition
  └──< media_assets

media_assets
  ├──< media_asset_locations
  └──< order_items

orders
  └──< order_items

download_links
  └──< download_accesses
```

---

# 4. Storage Strategy

## 4.1 Laravel disks

Retain separate Laravel storage disks:

```text
s3_concerts
s3_competitions
```

These may point to separate S3 buckets.

Separate buckets are acceptable and recommended because concerts and competitions are major business domains with potentially different:

- IAM permissions
- Retention rules
- Lifecycle rules
- Migration paths
- Operational access
- Cost reporting
- Archival policies

Do not split buckets by file type unless a later operational requirement justifies it.

Preferred boundary:

```text
concert bucket
  ├── photos
  └── videos

competition bucket
  ├── photos
  └── videos
```

## 4.2 Prefix stability

Storage prefixes should contain immutable identifiers.

Do not derive storage prefixes only from mutable names or dates.

Recommended examples:

```text
studios/{studio-uuid}/concerts/{concert-uuid}/
competitions/{competition-uuid}/
```

Within the entity prefix:

```text
photos/
videos/
thumbnails/
downloads/
manifests/
```

A readable slug may be included, but the immutable UUID should remain part of the path.



## 4.4 Concert media storage convention

Concert media is storage-derived by default.

A concert stores only:

- `storage_disk`
- `storage_prefix`

The application assumes the following folder convention beneath the concert prefix:

```text
videos/original/
videos/streaming/
photos/
```

The database does not require individual rows for videos or photos simply because they exist in S3.

`media_assets` are created only when a file requires durable business identity, for example:

- Orders
- Favourites
- Custom metadata
- Manual visibility control
- Archive tracking
- Processing state
- Stable external references

Original and streaming videos are not modelled as separate database collections or assets by default. Their relationship is inferred from the agreed folder structure and filename conventions.


## 4.3 Storage identity

A physical S3 object is identified by:

```text
storage_disk + full storage_key
```

Never use the filename alone as an identifier.

Repeated filenames such as `IMG_0001.jpg` are expected and safe because their full keys differ.

---

# 5. Proposed Migrations

The exact migration timestamps are intentionally omitted.

Suggested migration order:

```text
1. create_studios_table
2. create_concerts_table
3. create_media_collections_table
4. create_media_assets_table
5. create_media_asset_locations_table
6. create_customer_profiles_table
7. create_concert_access_grants_table
8. create_concert_accesses_table
9. add_concert_relationships_to_download_links_table
10. create_orders_table
11. create_order_items_table
```

The order and customer tables may be postponed if the first implementation focuses only on studios, concerts and media.

---

# 6. Studios Table

## 6.1 Purpose

A studio is a business entity that owns concerts.

A studio must exist independently of whether any studio representative has a user account.

## 6.2 Proposed schema

```php
Schema::create('studios', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    $table->string('name');
    $table->string('slug')->nullable()->index();
    $table->string('status')->default('active')->index();

    $table->string('contact_name')->nullable();
    $table->string('contact_email')->nullable()->index();
    $table->string('contact_phone')->nullable();

    $table->unsignedBigInteger('legacy_id')->nullable()->index();

    $table->text('notes')->nullable();

    $table->timestamps();
    $table->softDeletes();
});
```

## 6.3 Recommended status values

Application enum or constants:

```text
active
inactive
archived
```

Do not use a database enum.

## 6.4 Notes

- `legacy_id` supports V1 migration and reconciliation.
- Do not make `slug` globally authoritative.
- UUID should be used for public routes.
- Contact fields are business contact information, not authentication identity.

---

# 7. Concerts Table

## 7.1 Purpose

A concert belongs to one studio and stores the business metadata required to publish, protect, archive and manage that concert.

## 7.2 Proposed schema

```php
Schema::create('concerts', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    $table->foreignId('studio_id')
        ->constrained('studios')
        ->restrictOnDelete();

    $table->string('name');
    $table->string('slug')->nullable()->index();

    $table->string('status')->default('draft')->index();

    $table->date('event_date')->nullable()->index();
    $table->date('event_end_date')->nullable();

    $table->string('venue_name')->nullable();
    $table->text('description')->nullable();

    $table->string('storage_disk')->default('s3_concerts');
    $table->string('storage_prefix');

    $table->string('access_password_hash')->nullable();

    $table->timestamp('published_at')->nullable()->index();
    $table->timestamp('archived_at')->nullable()->index();

    $table->foreignId('created_by_user_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->foreignId('updated_by_user_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->unsignedBigInteger('legacy_id')->nullable()->index();

    $table->text('notes')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['studio_id', 'status']);
    $table->index(['storage_disk', 'storage_prefix']);
});
```

## 7.3 Recommended status values

```text
draft
processing
published
archived
```

Possible future values:

```text
restoring
unavailable
```

Use an application enum or constants.

## 7.4 Access password

The current V1 simple-password access model should be preserved.

The V2 password must be stored as a hash:

```php
Hash::make($plainPassword)
```

Validation should use:

```php
Hash::check($submittedPassword, $concert->access_password_hash)
```

Do not store a recoverable plain-text concert password.

## 7.5 Storage prefix

`storage_prefix` identifies the root S3 location for the concert.

Example:

```text
studios/{studio-uuid}/concerts/{concert-uuid}/
```

Media collections may point to folders beneath this prefix.

---

# 8. Media Collections Table

## 8.1 Purpose

A media collection represents a meaningful gallery, folder or media grouping associated with either a concert or competition.

Examples:

```text
Concert Videos
Concert Photos
Saturday Competition Photos
Awards Videos
Photographer A Gallery
```

The collection is always database-managed.

Its individual files may or may not have database records.

## 8.2 Ownership approach

Use explicit nullable foreign keys:

```text
concert_id
competition_id
```

Exactly one should be populated.

This is preferred over polymorphic ownership because the current major owners are known and explicit foreign keys are easier to query and validate.

Application validation must ensure:

```text
one and only one owner is set
```

A database check constraint may be added later after compatibility is confirmed.

## 8.3 Proposed schema

```php
Schema::create('media_collections', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    $table->foreignId('concert_id')
        ->nullable()
        ->constrained('concerts')
        ->cascadeOnDelete();

    $table->foreignId('competition_id')
        ->nullable();

    $table->string('name');
    $table->string('media_type')->index();

    $table->string('catalogue_mode')->default('storage')->index();

    $table->string('status')->default('draft')->index();
    $table->string('visibility')->default('private')->index();

    $table->string('storage_disk');
    $table->string('storage_prefix');

    $table->string('manifest_key')->nullable();

    $table->unsignedInteger('sort_order')->default(0);

    $table->timestamp('published_at')->nullable();
    $table->timestamp('archived_at')->nullable();

    $table->json('metadata')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['concert_id', 'media_type']);
    $table->index(['competition_id', 'media_type']);
    $table->index(['storage_disk', 'storage_prefix']);
});
```

## 8.4 Competition foreign key

The current V2 application has no competition database table.

Codex should not invent or create a competition schema as part of this migration unless separately requested.

For local experimentation, choose one of the following:

### Preferred temporary approach

Create `competition_id` as a nullable unsigned bigint without a foreign key:

```php
$table->unsignedBigInteger('competition_id')->nullable()->index();
```

Add the foreign key later when the V2 `competitions` table is formally designed.

### Alternative

Omit `competition_id` from the first migration and add it later.

The preferred option is to include the nullable indexed column now because competitions are already a confirmed domain.

## 8.5 Media type values

Recommended application values:

```text
photo
video
mixed
download
thumbnail
```

Most collections should be either `photo` or `video`.

## 8.6 Catalogue mode values

```text
storage
managed
hybrid
manifest
```

Meaning:

- `storage`: files are derived from S3.
- `managed`: files are expected to have `media_assets` rows.
- `hybrid`: some files have asset rows and others remain storage-derived.
- `manifest`: files are listed from a generated manifest.

Initial recommendation:

```text
Concert media: storage by default
Competition media: storage by default
Managed or hybrid mode: only when individual files require database-managed identity
```

## 8.7 Visibility values

```text
private
password
customer
public
```

These values describe collection visibility but do not replace concert access policies.

## 8.8 Status values

```text
draft
processing
published
archived
unavailable
```

---

# 9. Media Assets Table

## 9.1 Purpose

A media asset represents a file that requires stable identity or individual management.

Not every S3 object requires a media asset row.

Media asset records should commonly exist for:

- Videos
- Purchased photos
- Favourited photos
- Featured photos
- Individually hidden files
- Tagged files
- Files referenced by orders
- Files that may move between storage locations

## 9.2 Proposed schema

```php
Schema::create('media_assets', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    $table->foreignId('media_collection_id')
        ->constrained('media_collections')
        ->cascadeOnDelete();

    $table->string('media_type')->index();

    $table->string('storage_disk');
    $table->string('storage_key');

    $table->string('original_filename')->nullable();
    $table->string('display_name')->nullable();

    $table->string('status')->default('available')->index();
    $table->boolean('is_visible')->default(true)->index();

    $table->unsignedInteger('sort_order')->default(0);

    $table->unsignedBigInteger('size_bytes')->nullable();
    $table->unsignedInteger('duration_seconds')->nullable();

    $table->string('mime_type')->nullable();
    $table->string('extension')->nullable()->index();

    $table->string('thumbnail_storage_disk')->nullable();
    $table->string('thumbnail_storage_key')->nullable();

    $table->timestamp('verified_at')->nullable();
    $table->timestamp('missing_at')->nullable();
    $table->timestamp('archived_at')->nullable();

    $table->json('metadata')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->unique(['storage_disk', 'storage_key']);
    $table->index(['media_collection_id', 'sort_order']);
    $table->index(['media_collection_id', 'status']);
});
```

## 9.3 Stable identity rule

`media_assets.uuid` is the durable logical identity.

`storage_disk` and `storage_key` represent the current physical location.

If a file moves:

- Keep the same media asset UUID.
- Update its active location.
- Optionally record the old location in `media_asset_locations`.
- Do not rewrite completed order snapshots.

## 9.4 Promotion-on-interaction rule

For large photo galleries, photos may initially exist only in S3.

When a photo becomes commercially or operationally important:

1. Find a matching `media_assets` record by `storage_disk + storage_key`.
2. Create one if none exists.
3. Use the resulting `media_asset_id` for orders, favourites or durable references.

This avoids creating millions of low-value photo rows while preserving stable identity for important media.

---

# 10. Media Asset Locations Table

## 10.1 Purpose

This optional table tracks current and historical physical storage locations for a managed media asset.

It is useful when media may move between:

- Active bucket
- Archive bucket
- Restored bucket
- Replacement path

## 10.2 Proposed schema

```php
Schema::create('media_asset_locations', function (Blueprint $table) {
    $table->id();

    $table->foreignId('media_asset_id')
        ->constrained('media_assets')
        ->cascadeOnDelete();

    $table->string('storage_disk');
    $table->string('storage_key');

    $table->string('status')->default('active')->index();

    $table->timestamp('became_active_at')->nullable();
    $table->timestamp('retired_at')->nullable();

    $table->json('metadata')->nullable();

    $table->timestamps();

    $table->index(['media_asset_id', 'status']);
    $table->unique(['storage_disk', 'storage_key']);
});
```

## 10.3 Status values

```text
active
retired
missing
restoring
```

## 10.4 Implementation boundary

This table may be omitted from the first local implementation if it adds unnecessary complexity.

The minimum viable design may keep the active location directly on `media_assets`.

However, the service layer should be designed so location history can be introduced later.

---

# 11. Customer Profiles Table

## 11.1 Purpose

The existing `users` table remains the authentication identity for both staff and customers.

Customer-specific business data belongs in a separate profile table.

## 11.2 Users type

Retain the existing string `users.type` column.

Introduce application enum or constants:

```text
staff
customer
```

Possible future value:

```text
admin
```

Do not introduce a full roles and permissions package as part of this migration unless separately requested.

## 11.3 Proposed schema

```php
Schema::create('customer_profiles', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')
        ->unique()
        ->constrained('users')
        ->cascadeOnDelete();

    $table->string('preferred_name')->nullable();
    $table->string('phone')->nullable();

    $table->string('registration_source')->nullable()->index();

    $table->timestamp('terms_accepted_at')->nullable();
    $table->timestamp('privacy_accepted_at')->nullable();
    $table->timestamp('marketing_consent_at')->nullable();

    $table->json('preferences')->nullable();

    $table->timestamps();
    $table->softDeletes();
});
```

## 11.4 Customer account rule

Customer registration must remain optional.

Anonymous password access to a concert must continue to work without a customer account.

Customer accounts provide additional convenience:

- Save a concert
- Revisit archived concerts
- Build a customer mobile experience
- Maintain order history
- Retain access across devices
- Identify future access activity

---

# 12. Concert Access Grants Table

## 12.1 Purpose

A concert access grant represents durable permission for an email address or user to access a concert.

It is separate from temporary password-based access logging.

It supports:

- Optional account claiming
- Email invitations
- Saved concerts
- Future customer mobile access
- Revocation
- Expiry
- Legacy access import

## 12.2 Proposed schema

```php
Schema::create('concert_access_grants', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    $table->foreignId('concert_id')
        ->constrained('concerts')
        ->cascadeOnDelete();

    $table->foreignId('user_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->string('email')->nullable()->index();

    $table->string('source')->default('password')->index();
    $table->string('status')->default('active')->index();

    $table->foreignId('granted_by_user_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->timestamp('first_accessed_at')->nullable();
    $table->timestamp('last_accessed_at')->nullable();
    $table->timestamp('claimed_at')->nullable();
    $table->timestamp('expires_at')->nullable()->index();
    $table->timestamp('revoked_at')->nullable();

    $table->text('revoke_reason')->nullable();
    $table->json('metadata')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['concert_id', 'user_id']);
    $table->index(['concert_id', 'email']);
});
```

## 12.3 Source values

```text
password
invitation
staff_assignment
order
legacy_import
```

## 12.4 Status values

```text
active
claimed
expired
revoked
```

## 12.5 Claiming flow

A password-accessed concert may later be saved by a customer.

The implementation may:

1. Create a grant directly for the authenticated customer.
2. Create an email-based grant and attach the user later.
3. Update an existing email grant after login or registration.

Do not require an account merely to view a password-protected concert.

---

# 13. Concert Accesses Table

## 13.1 Purpose

`concert_accesses` records attempts to unlock or view a concert.

This is an analytics and security log, not the durable entitlement model.

It supports:

- Successful password unlocks
- Failed password attempts
- Registered account access
- Saved-access usage
- Staff access
- General usage numbers

## 13.2 Proposed schema

```php
Schema::create('concert_accesses', function (Blueprint $table) {
    $table->id();

    $table->foreignId('concert_id')
        ->constrained('concerts')
        ->cascadeOnDelete();

    $table->foreignId('user_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->foreignId('concert_access_grant_id')
        ->nullable()
        ->constrained('concert_access_grants')
        ->nullOnDelete();

    $table->string('access_method')->index();

    $table->timestamp('accessed_at')->index();
    $table->timestamp('last_seen_at')->nullable();

    $table->string('session_identifier')->nullable()->index();

    $table->ipAddress('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->text('referrer')->nullable();

    $table->boolean('was_successful')->default(true)->index();
    $table->string('failure_reason')->nullable();

    $table->timestamps();

    $table->index(['concert_id', 'accessed_at']);
});
```

## 13.3 Access method values

```text
password
account
saved_access
staff
```

## 13.4 Privacy note

Do not treat IP addresses as unique users.

IP address and user-agent data are approximate operational signals only.

Retention requirements should be reviewed before production rollout.

---

# 14. Existing Download Links Migration

## 14.1 Existing table

The existing `download_links` table is generic and must remain operational.

Current storage fields remain authoritative:

```text
storage_disk
storage_key
```

Do not replace or remove them.

## 14.2 Proposed additive migration

```php
Schema::table('download_links', function (Blueprint $table) {
    $table->foreignId('concert_id')
        ->nullable()
        ->after('generated_by_user_id')
        ->constrained('concerts')
        ->nullOnDelete();

    $table->foreignId('media_collection_id')
        ->nullable()
        ->after('concert_id')
        ->constrained('media_collections')
        ->nullOnDelete();

    $table->foreignId('media_asset_id')
        ->nullable()
        ->after('media_collection_id')
        ->constrained('media_assets')
        ->nullOnDelete();

    $table->foreignId('order_item_id')
        ->nullable()
        ->after('media_asset_id');

    $table->index(['concert_id', 'status']);
    $table->index(['media_asset_id', 'status']);
});
```

## 14.3 Order item foreign key

If `orders` and `order_items` are created after this migration, initially add `order_item_id` as:

```php
$table->unsignedBigInteger('order_item_id')->nullable()->index();
```

Then add the foreign key in a later migration.

Alternatively, postpone `order_item_id` until the ordering tables exist.

## 14.4 Compatibility rule

All existing download links remain valid with:

```text
concert_id = null
media_collection_id = null
media_asset_id = null
order_item_id = null
```

New concert downloads may set:

```text
concert_id
media_collection_id
media_asset_id when available
```

Storage disk and key remain required.

---

# 15. Orders Table

## 15.1 Purpose

An order represents a commercial transaction or purchase record.

The first order implementation may support customers ordering one or more individual photos.

A customer account should be preferred but may remain optional if the client requires guest ordering.

## 15.2 Proposed schema

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    $table->foreignId('user_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->string('customer_email')->nullable()->index();
    $table->string('customer_name')->nullable();

    $table->string('status')->default('draft')->index();

    $table->char('currency', 3)->default('AUD');
    $table->unsignedBigInteger('subtotal_amount')->default(0);
    $table->unsignedBigInteger('total_amount')->default(0);

    $table->timestamp('placed_at')->nullable()->index();
    $table->timestamp('paid_at')->nullable()->index();
    $table->timestamp('fulfilled_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();

    $table->json('metadata')->nullable();

    $table->timestamps();
    $table->softDeletes();
});
```

## 15.3 Monetary values

Store monetary amounts as integer minor units.

For AUD:

```text
1000 = $10.00
```

Do not use floating-point columns for money.

## 15.4 Status values

```text
draft
pending
paid
fulfilled
cancelled
refunded
```

Payment implementation is out of scope for this migration specification.

---

# 16. Order Items Table

## 16.1 Purpose

An order item identifies the logical media asset purchased and also preserves a historical snapshot of its storage location and display information.

## 16.2 Proposed schema

```php
Schema::create('order_items', function (Blueprint $table) {
    $table->id();

    $table->foreignId('order_id')
        ->constrained('orders')
        ->cascadeOnDelete();

    $table->foreignId('media_collection_id')
        ->nullable()
        ->constrained('media_collections')
        ->nullOnDelete();

    $table->foreignId('media_asset_id')
        ->nullable()
        ->constrained('media_assets')
        ->nullOnDelete();

    $table->string('snapshot_storage_disk');
    $table->string('snapshot_storage_key');

    $table->string('snapshot_filename')->nullable();
    $table->string('snapshot_display_name')->nullable();

    $table->string('item_type')->default('media')->index();

    $table->unsignedInteger('quantity')->default(1);
    $table->unsignedBigInteger('unit_price_amount')->default(0);
    $table->unsignedBigInteger('total_price_amount')->default(0);

    $table->json('metadata')->nullable();

    $table->timestamps();

    $table->index(['order_id', 'media_asset_id']);
    $table->index(['snapshot_storage_disk', 'snapshot_storage_key']);
});
```

## 16.3 Durable location behaviour

The order item should normally resolve fulfilment through:

```text
order_item.media_asset_id
    -> media_asset current location
```

The snapshot fields preserve:

- What was purchased
- The original location at purchase time
- The original filename and display name
- Historical evidence if the active asset later moves

Do not use the snapshot as the only active locator.

## 16.4 Promotion before purchase

When a customer orders a storage-only photo:

1. Resolve the selected collection.
2. Validate that the full key belongs beneath the collection prefix.
3. Find a media asset using `storage_disk + storage_key`.
4. Create the media asset if it does not exist.
5. Create the order item using the media asset ID.
6. Copy the current storage details into snapshot fields.

This promotes only commercially important photos into the managed asset catalogue.

---

# 17. Model Relationships

## 17.1 Studio

```php
public function concerts(): HasMany
{
    return $this->hasMany(Concert::class);
}
```

## 17.2 Concert

```php
public function studio(): BelongsTo
{
    return $this->belongsTo(Studio::class);
}

public function mediaCollections(): HasMany
{
    return $this->hasMany(MediaCollection::class);
}

public function accessGrants(): HasMany
{
    return $this->hasMany(ConcertAccessGrant::class);
}

public function accesses(): HasMany
{
    return $this->hasMany(ConcertAccess::class);
}

public function downloadLinks(): HasMany
{
    return $this->hasMany(DownloadLink::class);
}
```

## 17.3 MediaCollection

```php
public function concert(): BelongsTo
{
    return $this->belongsTo(Concert::class);
}

public function assets(): HasMany
{
    return $this->hasMany(MediaAsset::class);
}
```

Add the competition relationship once the V2 competition model exists.

## 17.4 MediaAsset

```php
public function collection(): BelongsTo
{
    return $this->belongsTo(MediaCollection::class, 'media_collection_id');
}

public function locations(): HasMany
{
    return $this->hasMany(MediaAssetLocation::class);
}

public function orderItems(): HasMany
{
    return $this->hasMany(OrderItem::class);
}
```

## 17.5 User

Add relationships without changing existing authentication behaviour:

```php
public function customerProfile(): HasOne
{
    return $this->hasOne(CustomerProfile::class);
}

public function concertAccessGrants(): HasMany
{
    return $this->hasMany(ConcertAccessGrant::class);
}

public function concertAccesses(): HasMany
{
    return $this->hasMany(ConcertAccess::class);
}

public function orders(): HasMany
{
    return $this->hasMany(Order::class);
}
```

## 17.6 Order

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function items(): HasMany
{
    return $this->hasMany(OrderItem::class);
}
```

## 17.7 OrderItem

```php
public function order(): BelongsTo
{
    return $this->belongsTo(Order::class);
}

public function mediaCollection(): BelongsTo
{
    return $this->belongsTo(MediaCollection::class);
}

public function mediaAsset(): BelongsTo
{
    return $this->belongsTo(MediaAsset::class);
}
```

---

# 18. Required Application Enums or Constants

Do not use database enums.

Suggested PHP enums:

```text
UserType
StudioStatus
ConcertStatus
MediaType
MediaCollectionStatus
MediaCollectionVisibility
MediaCatalogueMode
MediaAssetStatus
ConcertAccessMethod
ConcertAccessGrantStatus
ConcertAccessGrantSource
OrderStatus
```

Codex should place these according to the application's established feature-first structure.

---

# 19. Initial Functionality Boundary

The first implementation should focus on database and domain foundations.

## 19.1 Implement now

- Studio model, migration, factory and tests
- Concert model, migration, factory and tests
- Media collection model, migration, factory and tests
- Media asset model, migration, factory and tests
- Optional media asset location model
- Customer profile model and migration
- Concert access grant model and migration
- Concert access log model and migration
- Additive concert/media relationships on download links
- UUID generation and UUID route binding
- Application enums
- Model relationships
- Policies or basic staff-only authorisation foundations
- Validation that media collections have exactly one owner
- Validation that S3 object keys remain inside their collection prefix
- Hashing and checking concert passwords

## 19.2 May be scaffolded but not fully implemented

- Public concert password access
- Save concert to customer account
- Customer registration
- Customer “My Concerts”
- Orders and order items
- Photo promotion into media assets
- Archive restoration
- CloudFront delivery
- Mobile API endpoints
- Studio user management

## 19.3 Do not implement yet

- Payments
- Ticketing
- Performers
- Guardians
- Family accounts
- Individual dancer tagging
- Full roles and permissions hierarchy
- Studio portal accounts
- Automatic facial recognition
- Forced customer registration
- Destructive V1 cleanup
- V1 table removal

---

# 20. Suggested API and Admin Behaviour for Local Testing

These routes are only indicative and should follow the project's existing API conventions.

## 20.1 Admin studios

```text
GET    /api/admin/studios
POST   /api/admin/studios
GET    /api/admin/studios/{studio:uuid}
PATCH  /api/admin/studios/{studio:uuid}
```

## 20.2 Admin concerts

```text
GET    /api/admin/concerts
POST   /api/admin/concerts
GET    /api/admin/concerts/{concert:uuid}
PATCH  /api/admin/concerts/{concert:uuid}
```

## 20.3 Concert media collections

```text
GET    /api/admin/concerts/{concert:uuid}/media-collections
POST   /api/admin/concerts/{concert:uuid}/media-collections
PATCH  /api/admin/media-collections/{mediaCollection:uuid}
```

## 20.4 Collection objects

```text
GET /api/media-collections/{mediaCollection:uuid}/objects
```

Suggested query parameters:

```text
limit
continuation_token
```

The server must derive the disk and prefix from the collection record.

Do not accept an arbitrary public prefix that can escape the collection.

## 20.5 Managed assets

```text
POST /api/admin/media-collections/{mediaCollection:uuid}/assets/import
GET  /api/admin/media-collections/{mediaCollection:uuid}/assets
```

Import should be optional.

Videos may be imported as managed assets.

Photos should not be bulk-imported by default.

---

# 21. S3 Object Validation

Whenever the client submits a storage key associated with a media collection:

1. Load the collection from the database.
2. Read the collection's configured `storage_disk`.
3. Confirm the submitted key begins with the exact `storage_prefix`.
4. Reject traversal or cross-collection keys.
5. Confirm the object exists when the action requires a real file.
6. Never trust a client-supplied disk name.
7. Never use a basename as the identifier.

Example valid relationship:

```text
collection prefix:
competitions/abc/photos/day-1/

submitted key:
competitions/abc/photos/day-1/IMG_0001.jpg
```

Example invalid relationship:

```text
collection prefix:
competitions/abc/photos/day-1/

submitted key:
competitions/xyz/photos/IMG_0001.jpg
```

---

# 22. Photo Gallery Scalability

Do not return an entire large gallery in one response.

Use S3 continuation-token pagination.

Suggested response shape:

```json
{
  "success": true,
  "message": "Media collection objects returned.",
  "data": {
    "collection": {
      "uuid": "collection-uuid",
      "name": "Saturday Photos",
      "media_type": "photo"
    },
    "files": [
      {
        "name": "IMG_0001.jpg",
        "key": "competitions/abc/photos/day-1/IMG_0001.jpg",
        "extension": "jpg",
        "size": 4821932,
        "last_modified_at": "2026-07-17T04:00:00Z",
        "media_asset_uuid": null
      }
    ],
    "pagination": {
      "limit": 100,
      "next_token": "opaque-token",
      "has_more": true
    }
  }
}
```

If a file already has a media asset row, return its UUID.

Large-scale improvements that may be added later:

- Thumbnails
- Cached listings
- Generated manifests
- Search indexes
- CloudFront delivery
- Archive restoration state

---

# 23. V1 Migration Preparation

Do not perform the V1 data migration as part of the initial schema implementation.

Prepare for later migration using:

```text
studios.legacy_id
concerts.legacy_id
```

Future migration flow:

1. Import studios from V1.
2. Import concerts from V1.
3. Preserve or map existing UUIDs.
4. Convert plain or legacy concert passwords to secure hashes where possible.
5. Assign storage disks and prefixes.
6. Create media collections for existing video and photo folders.
7. Compare V1 video rows with actual S3 objects.
8. Import selected videos as managed media assets.
9. Leave V1 tables untouched.
10. Generate a reconciliation report for missing or unmatched media.

No destructive migration from V1 is allowed during this phase.

---

# 24. Testing Requirements

Codex should include feature and unit tests covering at least:

## Studios

- Studio can be created.
- Studio UUID is generated.
- Studio soft deletion works.
- Studio can have many concerts.

## Concerts

- Concert belongs to a studio.
- Concert password is hashed.
- Concert password can be validated.
- Concert UUID route binding works.
- Concert storage prefix is required.
- Concert soft deletion does not delete the studio.

## Media collections

- Collection belongs to a concert.
- Exactly one owner is required.
- Collection disk and prefix are required.
- Collection UUID route binding works.
- Storage mode and media type validation work.

## Media assets

- Asset belongs to a collection.
- Disk and full key are unique together.
- Repeated basenames are allowed when full keys differ.
- Asset UUID remains stable.
- Asset can update its active storage location.

## Customer access

- Anonymous concert access can be logged.
- Registered customer access can be logged.
- Access grants may exist for email without user.
- Access grants may later be claimed by a user.

## Download links

- Existing download-link creation still works without concert relationships.
- A download link may reference a concert.
- A download link may reference a media collection.
- A download link may reference a media asset.
- Deleting a referenced business record sets nullable relationships to null.

## Orders

- Storage-only photo can be promoted into a media asset.
- Order item stores media asset ID.
- Order item stores snapshot disk and key.
- Current fulfilment resolves through the media asset.
- Repeated camera filenames do not collide across full keys.

---

# 25. Recommended Codex Implementation Instructions

Use the following implementation constraints:

1. Follow the project's existing feature-first architecture.
2. Inspect existing migrations, models, factories, resources, actions and tests before adding code.
3. Do not modify existing production columns destructively.
4. Do not remove or rename existing download-link fields.
5. Keep all new foreign keys nullable when attached to existing tables.
6. Use Laravel model relationships and typed enums where appropriate.
7. Use UUID public route binding.
8. Use soft deletion for business entities.
9. Do not implement payments or a full customer portal.
10. Do not bulk-create media asset rows for every S3 photo.
11. Add tests for all migrations and important constraints.
12. Run the full test suite.
13. Do not commit changes automatically.
14. Present the planned file changes and ask for approval before committing.

---

# 26. Recommended Initial Delivery Split

## Delivery A: Core domain

Implement:

```text
studios
concerts
media_collections
media_assets
```

Also implement:

- Enums
- Relationships
- Factories
- Seeders for local demonstration
- Tests
- Basic admin CRUD
- Collection object listing from the configured S3 disk and prefix

## Delivery B: Access and download integration

Implement:

```text
customer_profiles
concert_access_grants
concert_accesses
download_links additive columns
```

Also implement:

- Concert password validation
- Anonymous access logging
- Optional save-to-account flow
- Customer concert relationship
- Concert-linked download reporting

## Delivery C: Ordering foundation

Implement:

```text
orders
order_items
media asset promotion
```

Also implement:

- Select an S3-derived photo
- Promote it into `media_assets`
- Add it to an order
- Resolve current media location through the asset
- Preserve order snapshot details
- Generate authorised download links after fulfilment rules are defined

---

# 27. Final Architecture Decision Summary

The agreed design is:

1. Studios and concerts are database-managed business entities.
2. A studio owns many concerts.
3. Concerts and competitions use separate major media domains.
4. Separate concert and competition S3 buckets are acceptable and recommended.
5. Both buckets may contain photos and videos.
6. Every meaningful gallery or folder has a `media_collections` record.
7. Not every S3 photo requires a database row.
8. Videos and photos are storage-derived by default.
9. Individual files are promoted into `media_assets` only when they require durable business identity.
10. Purchased or otherwise important media is promoted into `media_assets`.
11. A media asset UUID is the durable logical identity.
12. A media asset stores its current physical location.
13. An order item stores both the media asset relationship and a historical location snapshot.
14. Customer registration remains optional.
15. Password-protected anonymous concert access remains supported.
16. Customer accounts add saved concerts, archive access, mobile-app support and order history.
17. Existing production tables must only receive additive, non-destructive changes.
18. Existing generic download links remain valid and reusable.