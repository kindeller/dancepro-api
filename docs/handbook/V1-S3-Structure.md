# DancePro V1 S3 Structure

## Purpose

This document records the canonical Amazon S3 object-key structure used by the
legacy DancePro V1 application. It exists as a stable reference for migration,
compatibility and future V2 storage-design work.

This is a historical description, not the target V2 storage design.

## Bucket

The V1 concert media bucket is:

```text
dance-pro-videos
```

Do not place credentials, signed URLs or other secret configuration in
documentation or application responses.

## Collection Identifier

The V1 database stored a collection UUID. That value identified the matching
top-level S3 prefix containing the collection's files.

The legacy UUID format is 32 lowercase hexadecimal characters without hyphens:

```text
{collection_uuid}/
```

Example using placeholders only:

```text
0123456789abcdef0123456789abcdef/
```

S3 prefixes are logical key prefixes rather than physical directories. Code
reading V1 data must treat the complete object key as the durable storage
location.

## Canonical Layout

A normal V1 collection contains original media directly below its UUID prefix
and generated thumbnails below a `thumbs/` child prefix:

```text
dance-pro-videos/
└── {collection_uuid}/
    ├── {sequence} {display_name}.mp4
    ├── {sequence} {display_name}.mp4
    ├── thumbs/
    │   ├── thumb-{sequence} {display_name}.png
    │   └── thumb-{sequence} {display_name}.png
    └── {optional_collection_download}.zip
```

Some collections may also contain a PDF or ZIP deliverable directly below the
UUID prefix. These are collection-level files and should not be assumed to
have a thumbnail.

## Key Conventions

- The database collection UUID maps to the first component of the S3 key.
- Original videos are normally MP4 files directly below the UUID prefix.
- Video filenames commonly begin with an ordering sequence followed by a
  human-readable display name.
- PNG thumbnails are stored under `{collection_uuid}/thumbs/`.
- Thumbnail filenames commonly use the `thumb-` prefix and correspond to an
  original video's sequence and display name.
- ZIP and PDF files may represent collection-level download material.
- Original filenames can contain spaces and must not be reconstructed by
  normalising or slugifying display text.

Example keys using synthetic values only:

```text
0123456789abcdef0123456789abcdef/01 Opening Performance.mp4
0123456789abcdef0123456789abcdef/thumbs/thumb-01 Opening Performance.png
0123456789abcdef0123456789abcdef/Complete Collection.zip
0123456789abcdef0123456789abcdef/Event Program.pdf
```

## V2 Migration Interpretation

When V2 reads or migrates a V1 collection:

1. Use the database UUID to establish the allowed collection prefix.
2. Enumerate objects only within `{collection_uuid}/`.
3. Treat files directly below that prefix as original or collection-level
   assets according to their media type.
4. Treat objects below `thumbs/` as derived thumbnail assets.
5. Preserve the full source object key as migration provenance.
6. Do not infer ownership from a filename; ownership comes from the database
   record and its UUID-to-prefix relationship.
7. Do not expose the bucket directly as the public authorization boundary.

V2 may introduce a different managed layout. Any migration must keep an
explicit mapping from the V1 bucket and source key to the corresponding V2
collection, asset and location records.

## Scope

This document intentionally records only the normal V1 production convention.
Historical test prefixes, experiments and malformed or exceptional keys are
outside its scope and must not be used as templates for V2.

