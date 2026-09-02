<?php

namespace App\Features\Venues\Support;

class VenueCatalog
{
    /** @return array<int, array<string, mixed>> */
    public static function venues(): array
    {
        return [
            self::venue('Abbey Beach Resort', '595 Bussell Highway', 'Broadwater', map: 'ABBEY.jpg'),
            self::venue('Bunbury Regional Entertainment Centre', '2 Blair Street', 'Bunbury', '6230', map: 'BREC.jpg'),
            self::venue('Centenary Pavilion - Claremont Showgrounds', 'Ashton Avenue', 'Claremont', map: 'CENTENARY.jpg'),
            self::venue('Caroline Payne Theatre - Corpus Christi College', '50 Murdoch Drive', 'Bateman', '6150', map: 'CORPUS CHRISTI.jpg', operational: 'Tiered seating.'),
            self::venue('Crown Theatre', 'Great Eastern Highway', 'Burswood', '6100', parking: 'Free and paid parking onsite.', aliases: ['Fictional Crown Theatre']),
            self::venue('John Curtin College of the Arts - Curtin Theatre', '90 Ellen Street', 'Fremantle', '6160', parking: 'Free parking onsite.', map: 'JOHN CURTIN.jpg', operational: 'Very dark around the edges of the stage.'),
            self::venue('EDC Studios', '15 Dickens Place', 'Armadale'),
            self::venue('Emmanuel Catholic College', '122 Hammond Road', 'Success', '6164', map: 'ECC.jpg', aliases: ['Fictional Success College Theatre']),
            self::venue('Flash Studios', '1/257 Balcatta Road', 'Balcatta'),
            self::venue('Hype Dance Academy', '1/101 Windsor Road', 'Wangara', map: 'HYPE.jpg'),
            self::venue('John Wollaston Anglican Community School Gym', '7 Centre Road', 'Camillo', map: 'John Wollaston.jpg'),
            self::venue("Joy Shepherd Performing Arts Centre - St Hilda's Anglican School for Girls", 'Glyde Street', 'Mosman Park', map: 'ST HILDAS.jpg'),
            self::venue('Kalamunda Performing Arts Centre', '48 Canning Road', 'Kalamunda', map: 'KPAC.jpg'),
            self::venue('Kennedy Baptist College Auditorium', 'Farrington Road', 'Murdoch', '6150', map: 'KENNEDY.jpg'),
            self::venue('Kingsway Christian College Auditorium', '157 Kingsway', 'Darch', map: 'KINGSWAY.jpg', reference: 'DP Theatre Seating - Kingsway Christian College copy.jpg', access: 'Venue access is from Westport Parade.'),
            self::venue('Kingsway Indoor Stadium', '130 Kingsway', 'Madeley', map: 'KIS.jpg'),
            self::venue('Koorliny Arts Centre', '10 Hutchins Way', 'Kwinana Town Centre', '6167', map: 'KOORLINY.jpg'),
            self::venue('Lakes Theatre - Mandurah Baptist College', 'Catalina Drive', 'Lakelands', map: 'LAKES.jpg', aliases: ['Fictional Lakes Theatre']),
            self::venue('Lumen Christi College Performing Arts Centre', '81 Station Street', 'Martin', parking: 'Free parking onsite.', access: 'Enter from the Mills Road entrance.', map: 'LUMEN.jpg', operational: 'Lighting colours can be problematic.'),
            self::venue('Mandurah Performing Arts Centre', '9 Ormsby Terrace', 'Mandurah', '6210', parking: 'Free parking onsite.', map: 'MANPAC.jpg', aliases: ['Fictional Mandurah Performing Arts Centre']),
            self::venue('Morley Sport and Recreation Centre', '12 Wellington Road', 'Morley', '6062', map: 'MORLEY.jpg', operational: 'All activity is in one room. Front-row seats may need to be removed.'),
            self::venue('Marist Auditorium - Newman College', '216 Empire Avenue', 'Churchlands', '6018', map: 'NEWMAN.jpg', operational: 'Seats move when people walk.'),
            self::venue('Nexus Theatre - Murdoch University', '90 South Street', 'Murdoch', map: 'NEXUS.jpg', operational: 'Arrange for the XLR sound feed to be dropped from the sound box. The feed can have feedback or buzzing, so venue staff may need to add a filter.'),
            self::venue('Northam Senior High School', 'Kennedy Street', 'Northam', map: 'NORTHAM SHS.jpg'),
            self::venue('Octagon Theatre - UWA', '35 Stirling Highway', 'Crawley', '6009', map: 'OCTAGON THEATRE.jpg'),
            self::venue('Redmond Theatre - Prendiville Catholic College', null, 'Ocean Reef'),
            self::venue('Quarry Amphitheatre', '1 Waldron Drive', 'City Beach', map: 'QUARRY.jpg', operational: 'Outdoor venue.'),
            self::venue('Quinns Baptist College', '8 Salerno Drive', 'Mindarie', '6030', map: 'QUINNS.jpg'),
            self::venue('Regal Theatre', '474 Hay Street', 'Subiaco', '6008', access: 'Venue access is from Alvan Street.', map: 'REGAL.jpg', aliases: ['Fictional Regal Theatre']),
            self::venue('Rixon Theatre - Penrhos College', 'Thelma Street', 'Como', '6152', parking: 'Free parking onsite.', access: 'Enter via Throssell Street.', map: 'RIXON.jpg'),
            self::venue('Sacred Heart College', 'Hocking Parade', 'Sorrento', map: 'SACRED HEART.jpg'),
            self::venue('Dianella Secondary College', null, 'Dianella'),
            self::venue('Swan Park Theatre - Calisthenics Centre', 'Gray Drive', 'Midvale', '6056', parking: 'Free parking onsite.', map: 'SWAN PARK.jpg'),
            self::venue('Harrisdale Senior High School', null, 'Harrisdale'),
            self::venue('Georgiana Molloy Anglican School', 'Joseph Drive', 'Yalyalup', '6280', map: 'GMAS.jpg'),
            self::venue('Studio 24', '24/3 South Street', 'Canning Vale', map: 'S24.jpg'),
            self::venue('Stirling Adriatic Club', '78 Jones Street', 'Stirling', map: 'STIRLING.jpg'),
            self::venue('Vasto Club', '5 Vasto Place', 'Balcatta', map: 'VASTO.jpg'),
            self::venue('WA Performance School - Lords Recreation Centre', '3 Price Street', 'Subiaco', map: 'WAPS.jpg'),
            self::venue("Lady Wardle Performing Arts Centre - St Mary's Anglican Girls' School", '75 Elliott Road', 'Karrinyup', map: 'WARDLE.jpg'),
            self::venue('WA Stage School', '2/500 Marmion Street', 'Booragoon', map: 'WASS.jpg'),
        ];
    }

    /** @return array<string, mixed> */
    private static function venue(
        string $name,
        ?string $address,
        ?string $suburb,
        ?string $postcode = null,
        ?string $parking = null,
        ?string $access = null,
        ?string $map = null,
        ?string $reference = null,
        ?string $operational = null,
        array $aliases = [],
    ): array {
        return compact('name', 'address', 'suburb', 'postcode', 'parking', 'access', 'map', 'reference', 'operational', 'aliases');
    }
}
