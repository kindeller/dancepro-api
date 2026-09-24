<?php

namespace App\Features\Tracking\Services;

use Illuminate\Support\Str;

class EmailContentFormatter
{
    public function html(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
    }
}
