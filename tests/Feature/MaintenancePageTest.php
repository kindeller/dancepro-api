<?php

test('the maintenance page clearly explains the temporary outage', function () {
    $view = $this->view('errors.503');

    $view
        ->assertSee('DancePro')
        ->assertSee('temporarily unavailable')
        ->assertSee('no longer than 30 minutes')
        ->assertSee('There’s no need to contact us')
        ->assertSee('storage/1024.png');
});
