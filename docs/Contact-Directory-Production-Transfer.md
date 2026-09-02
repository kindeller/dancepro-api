# Contact Directory Production Transfer

Studio and competition contacts and their logos are application data. They are not deployed through Git.

The transfer archive contains names, email addresses and phone numbers. Keep it in private storage, transfer it over an encrypted channel, restrict access and remove temporary copies after the production import and backup have been verified.

## Export the reviewed local directory

```bash
sail artisan contacts:export
```

The default output is `storage/app/private/imports/contact-directory.zip`. The archive contains a versioned JSON manifest and integrity-checked logo files. It includes active and inactive records so the production directory matches the reviewed local directory.

## Validate on production

Copy the archive to the same private path on the production server, then run the non-writing validation:

```bash
sail artisan contacts:import
```

Set `CONTACT_DIRECTORY_LOGO_DISK` to a durable, browser-accessible filesystem disk before applying the import. A single-server installation with persistent shared storage can use `public`; multi-server or replaceable-server deployments should use an appropriately configured object-storage disk.

Review the reported new and updated record counts. The command rejects unsupported archives, malformed contacts, missing or modified logos, duplicate codes and records that have the same name or code as a different existing UUID.

## Apply on production

After a verified database and storage backup exists and the dry-run counts are correct:

```bash
sail artisan contacts:import --apply --force
```

The import is repeatable. Records are matched by stable UUID, soft-deleted matching records are restored, contact/staff rows in the archive replace those belonging to each matched record, and unrelated production records are not removed. Imported logos use content-addressed filenames; older files are retained for recovery or later maintenance.

Verify the Active and Inactive totals and several contact/logo records in both Admin and My Crew before removing the transferred archive.
