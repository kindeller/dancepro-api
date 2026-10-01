<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The public homepage introduces DancePro and links into media discovery.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Your performance. Yours to keep.')
            ->assertSee(route('guest-galleries.index'));

        $this->get(route('studios.index'))->assertOk()->assertSee('Find your dance studio.');
    }
}
