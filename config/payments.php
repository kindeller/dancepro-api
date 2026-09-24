<?php

return [
    'driver' => env('PAYMENT_DRIVER', in_array(env('APP_ENV'), ['local', 'testing'], true) ? 'fake' : 'disabled'),
];
