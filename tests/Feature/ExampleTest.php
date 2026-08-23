<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The public homepage is the studio discovery experience.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Find your dance studio.');
    }
}
