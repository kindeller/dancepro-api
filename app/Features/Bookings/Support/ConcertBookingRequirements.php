<?php

namespace App\Features\Bookings\Support;

class ConcertBookingRequirements
{
    public const PORTRAIT = [
        'portrait_space' => 'Portrait sessions will have a powered 12 × 10 metre space and dancers will be brought out in costume.',
        'no_personal_backdrop_photos' => 'Parents and dancers will not take personal photos on the portrait backdrop.',
    ];

    public const PHOTO = [
        'minimum_spend' => 'I understand the photography minimum spend is $800 AUD for the first photography event and $600 AUD for each additional photography event, including DR Portrait sessions.',
        'photographer_seat' => 'A suitable auditorium position or seat will be arranged for the photographer.',
        'no_audience_recording' => 'Audience members will be told that personal filming and photography are not permitted.',
        'promote_gallery' => 'DancePro service information and the unique gallery login will be shared with families and printed in the programme.',
        'credit_images' => 'Published or printed DancePro images will credit or link to DancePro.',
    ];

    public const VIDEO = [
        'video_access' => 'Videography bookings will provide theatre access 1.5–2 hours before the concert.',
        'video_space_audio' => 'Videography bookings will provide 5–6 adjacent seats, an unobstructed view, and an arranged XLR sound feed.',
        'programme_invoice' => 'The current programme will be supplied and the invoice finalised before video release.',
    ];

    public static function all(): array
    {
        return ['accurate' => 'The information provided is accurate and changes will be sent to DancePro promptly.']
            + self::PORTRAIT + self::PHOTO + self::VIDEO;
    }
}
