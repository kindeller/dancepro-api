<?php

namespace Tests\Feature;

use Tests\TestCase;

class MaintenancePageTest extends TestCase
{
    public function test_maintenance_page_clearly_explains_the_temporary_outage(): void
    {
        $this->view('errors.503')
            ->assertSee('DancePro')
            ->assertSee('temporarily unavailable')
            ->assertSee('no longer than 30 minutes')
            ->assertSee('There’s no need to contact us')
            ->assertSee('storage/1024.png');
    }
}
