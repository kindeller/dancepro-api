<?php

namespace Tests\Unit\Features\Customers;

use App\Features\Customers\Support\CustomerDancerNameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CustomerDancerNameMatcherTest extends TestCase
{
    #[DataProvider('matchingNames')]
    public function test_it_matches_complete_names_across_space_and_hyphen_variants(string $title, string $name): void
    {
        $this->assertTrue((new CustomerDancerNameMatcher)->matches($title, $name));
    }

    public static function matchingNames(): array
    {
        return [
            ['01 Ella-Rose Briggs & Sophie-Anne Briggs', 'Ella Rose Briggs'],
            ['01 Ella Rose Briggs & Sophie Anne Briggs', 'Ella-Rose Briggs'],
            ['01 ELLA–ROSE BRIGGS & Sophie Briggs', 'ella rose briggs'],
            ['01 Aria McIntosh Isla Hardy & Sophie Trewin', 'Sophie Trewin'],
        ];
    }

    public function test_it_recognises_a_complete_studio_name_at_the_start_of_a_troupe_title(): void
    {
        $matcher = new CustomerDancerNameMatcher;

        $this->assertTrue($matcher->startsWith('Dance Fit Dance Academy Whip It', 'Dance Fit Dance Academy'));
        $this->assertTrue($matcher->startsWith('Dance-Fit Dance Academy Whip It', 'Dance Fit Dance Academy'));
        $this->assertFalse($matcher->startsWith('Other Studio Dance Fit Dance Academy', 'Dance Fit Dance Academy'));
        $this->assertFalse($matcher->startsWith('Dance Fitness Academy Whip It', 'Dance Fit'));
    }

    public function test_it_does_not_match_a_partial_name(): void
    {
        $this->assertFalse((new CustomerDancerNameMatcher)->matches('01 Annabelle Jones & Mia West', 'Anna'));
    }
}
