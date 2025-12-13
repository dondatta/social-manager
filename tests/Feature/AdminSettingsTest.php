<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AutomationSetting;
use Livewire\Livewire;
use App\Filament\Pages\Settings;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_settings()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('data.story_reply_template', 'Thanks for the reply, {first_name}!')
            ->set('data.welcome_dm_delay', 10)
            ->set('data.welcome_dm_template', 'Welcome, {first_name}!')
            ->set('data.comment_keyword', 'GUIDE')
            ->set('data.comment_dm_template', 'Here is the guide!')
            ->set('data.story_mention_template', 'Thanks for the mention!')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('automation_settings', [
            'key' => 'story_reply_template',
            'value' => 'Thanks for the reply, {first_name}!',
        ]);
    }
}
