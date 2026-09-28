<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class AutomationTest extends TestCase
{
    public function test_user_can_create_automation()
    {
        $user = User::first();
        if (!$user) $this->markTestSkipped('No user');

        $response = $this->actingAs($user)->post('/automations', [
            'name' => 'Test Automation',
            'trigger_type' => 'subscribed',
            'is_active' => '1',
            'steps' => [
                ['type' => 'wait', 'config' => ['amount' => 1, 'unit' => 'days']],
                ['type' => 'send_email', 'config' => ['campaign_id' => 1]],
            ]
        ]);

        $response->assertRedirect('/automations');
        $this->assertDatabaseHas('automations', ['name' => 'Test Automation']);
        $this->assertDatabaseHas('automation_steps', ['type' => 'wait']);
        $this->assertDatabaseHas('automation_steps', ['type' => 'send_email']);
    }

    public function test_user_can_create_contact_with_tags()
    {
        $user = User::first();
        if (!$user) $this->markTestSkipped('No user');

        $response = $this->actingAs($user)->post('/contacts', [
            'email' => 'test2000_tags@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'tags' => 'vip, new_lead'
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', ['email' => 'test2000_tags@example.com']);
        $this->assertDatabaseHas('tags', ['name' => 'vip']);
        $this->assertDatabaseHas('tags', ['name' => 'new_lead']);
    }
}
