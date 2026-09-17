<?php

return [
    'upload_disk' => env('MEDIA_UPLOAD_DISK', 's3_concerts'),
    'legacy_disk' => env('MEDIA_LEGACY_DISK', 's3_concerts_legacy'),
    'upload_url_ttl_minutes' => (int) env('MEDIA_UPLOAD_URL_TTL_MINUTES', 15),
    'upload_record_ttl_hours' => (int) env('MEDIA_UPLOAD_RECORD_TTL_HOURS', 24),
    'object_ref_ttl_minutes' => (int) env('MEDIA_OBJECT_REF_TTL_MINUTES', 15),
    'max_files_per_batch' => (int) env('MEDIA_MAX_FILES_PER_BATCH', 100),
    'max_single_upload_bytes' => (int) env('MEDIA_MAX_SINGLE_UPLOAD_BYTES', 104857600),
    'max_batch_bytes' => (int) env('MEDIA_MAX_BATCH_BYTES', 10737418240),
    'max_object_bytes' => (int) env('MEDIA_MAX_OBJECT_BYTES', 107374182400),
    'multipart_part_size_bytes' => (int) env('MEDIA_MULTIPART_PART_SIZE_BYTES', 67108864),
    'max_parts_per_request' => (int) env('MEDIA_MAX_PARTS_PER_REQUEST', 100),
    'max_manifest_bytes' => (int) env('MEDIA_MAX_MANIFEST_BYTES', 2097152),
    'max_hls_objects' => (int) env('MEDIA_MAX_HLS_OBJECTS', 10000),
    'max_hls_variants' => (int) env('MEDIA_MAX_HLS_VARIANTS', 4),
    'legacy_page_size' => (int) env('MEDIA_LEGACY_PAGE_SIZE', 100),
];
