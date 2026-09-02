<?php

return [
    /*
    | The selected disk must provide durable storage and a browser-accessible URL
    | in production. Local development uses Laravel's public disk by default.
    */
    'logo_disk' => env('CONTACT_DIRECTORY_LOGO_DISK', 'public'),
];
