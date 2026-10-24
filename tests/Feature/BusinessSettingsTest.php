<?php

use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

test('the admin can change the business details shown on the website', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.settings')
        ->set('values.business_name', 'Lone Star Homes')
        ->set('values.phone', '(512) 555-0199')
        ->set('values.mortgage_rate', '5.75')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('business_name'))->toBe('Lone Star Homes');

    $this->get(route('home'))->assertSee('Lone Star')->assertSee('(512) 555-0199');
});

test('business details are checked before saving', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.settings')
        ->set('values.business_name', '')
        ->set('values.email', 'nope')
        ->set('values.facebook_url', 'javascript:alert(1)')
        ->call('save')
        ->assertHasErrors(['values.business_name', 'values.email', 'values.facebook_url']);
});

test('the privacy page uses the owner’s own policy when there is one', function () {
    Setting::put(['privacy_policy' => "## Our promise\n\nWe never sell your data. <script>alert(1)</script>"]);

    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Our promise')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});
