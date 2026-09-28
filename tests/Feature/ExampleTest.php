<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * `/` is the public marketing page: guests must be able to read it without
     * being bounced to a login form.
     */
    public function test_the_application_root_is_public_for_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('EliteSender', escape: false);
    }

    /** Signed-in users should land in the product, not on the sales page. */
    public function test_the_application_root_redirects_authenticated_users_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_offline_page_returns_200(): void
    {
        $this->get('/offline')->assertStatus(200);
    }
}
