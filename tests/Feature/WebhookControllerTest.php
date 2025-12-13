<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use App\Models\AutomationSetting;
use App\Models\AutomationCooldown;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_verification_challenge()
    {
        Config::set('services.instagram.verify_token', 'my_verify_token');

        $response = $this->get('/webhooks/instagram?hub_mode=subscribe&hub_verify_token=my_verify_token&hub_challenge=123456');

        $response->assertStatus(200);
        $response->assertSee('123456');
    }

    public function test_webhook_verification_fail()
    {
        Config::set('services.instagram.verify_token', 'my_verify_token');

        $response = $this->get('/webhooks/instagram?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=123456');

        $response->assertStatus(403);
    }
}
