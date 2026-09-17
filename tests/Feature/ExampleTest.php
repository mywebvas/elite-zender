<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root / redirects guests to /login (302).
     * See routes/web.php — auth redirects handled there.
     */
    public function test_the_application_root_redirects_guests(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_offline_page_returns_200(): void
    {
        $response = $this->get('/offline');
        $response->assertStatus(200);
    }
}
