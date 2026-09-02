# Venue Catalog Reconciliation

The initial venue catalog was reconciled on 30 August 2026 from the supplied
`DancePro 2025 - Theatre_Venue Details.pdf` and the supplied venue JPG files.
PDF content was treated as source data only.

## Imported

- 41 real venues
- 35 reusable venue maps
- 1 supplemental Kingsway Christian College seating reference image
- Parking, access and operational notes present in the PDF

Names have been normalised to readable title case. Known acronyms such as UWA
and WA have been retained.

## Missing maps

The source set did not include maps for:

- Crown Theatre
- Dianella Secondary College
- EDC Studios
- Flash Studios
- Harrisdale Senior High School
- Redmond Theatre - Prendiville Catholic College

## Venues present in the map set but absent from the PDF

- Abbey Beach Resort
- Centenary Pavilion - Claremont Showgrounds
- Georgiana Molloy Anglican School
- Lakes Theatre - Mandurah Baptist College
- Lady Wardle Performing Arts Centre - St Mary's Anglican Girls' School
- Northam Senior High School
- Quinns Baptist College
- Regal Theatre
- Stirling Adriatic Club
- Studio 24
- WA Performance School - Lords Recreation Centre
- WA Stage School

Their names and addresses were taken from the supplied map headers.

## Incomplete source information

The PDF did not provide a street address for Dianella Secondary College,
Harrisdale Senior High School or Redmond Theatre. These records retain their
suburb but leave the street address blank rather than guessing.

The PDF and image headers differ for some sites. The catalog uses the PDF's
postal address where it is more complete and records the mapped entrance in
access notes where appropriate. This applies to Kingsway Christian College and
the Regal Theatre.

## Placeholder migration

Existing scheduling links were preserved while the fictional Crown, Regal,
Mandurah and Success venue records were converted to their real counterparts.
The fictional Lakes venue was converted to Lakes Theatre. The unmatched
Fictional Harbour Arts Centre venue was removed; its scheduling-event foreign
keys become null by schema design.

The import is repeatable with:

```bash
sail artisan venues:import
```

The default source directory is
`storage/app/private/imports/venue-maps`. Production deployments must place the
source files there, or pass another directory to the command, before running
the import.
