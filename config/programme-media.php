<?php

return [
    // This first transport targets private local storage. Production activation
    // requires an explicit opt-in and persistent storage/deployment review.
    'uploads_enabled' => env('PROGRAMME_PHOTO_UPLOADS_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),
    'max_image_pixels' => 60000000,
];
