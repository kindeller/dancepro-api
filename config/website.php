<?php

return [
    'photography' => json_decode(file_get_contents(resource_path('content/website-photography.json')), true, flags: JSON_THROW_ON_ERROR),
    'heroes' => [
        'home' => 'home-performance', 'about' => 'joy', 'services' => 'spotlight',
        'book' => 'ensemble', 'competition' => 'duet', 'portfolio' => 'portfolio',
        'pose' => 'outdoor', 'shootme' => 'headshot-hero', 'promo' => 'studio-promo',
        'studio' => 'studio-portrait', 'shop' => 'white-ballet', 'terms' => 'silhouette',
        'privacy' => 'light', 'help' => 'ballet',
    ],
    'stories' => [
        'services' => [
            '/book' => 'formation', '/competition' => 'soar', '/portfolio' => 'park-balance',
            '/pose' => 'mini-session', '/shootme' => 'headshot', '/promo' => 'studio-rehearsal',
            '/studio' => 'studio-leap', '/shop' => 'portrait',
        ],
    ],
    'details' => [
        'book' => 'rhythm', 'competition' => 'precision', 'portfolio' => 'laneway-portrait',
        'pose' => 'studio-poses', 'shootme' => 'pink-portrait', 'promo' => 'studio-team',
        'studio' => 'costume-portrait', 'shop' => 'flow',
    ],
];
