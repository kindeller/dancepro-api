<?php

return [
    /* Public logos and reference images must use durable shared storage in production. */
    'public_disk' => env('PUBLIC_UPLOAD_DISK', env('CONTACT_DIRECTORY_LOGO_DISK', 'public')),
];
