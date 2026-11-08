<?php

use App\Enums\EnquiryStatus;
use App\Models\Agent;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;

test('a first visit is counted once with where it came from', function () {
    $this->withHeader('Referer', 'https://www.google.com/search?q=homes')->get(route('home'))->assertOk();
    $this->get(route('cities.index'))->assertOk();

    $visit = Visit::query()->sole();

    expect($visit->source)->toBe('Google')->and($visit->views)->toBe(1);
});

test('staff and bots are not counted', function () {
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get(route('home'));
    $this->actingAs(User::factory()->admin()->create())->get(route('home'));

    expect(Visit::query()->count())->toBe(0);
});

test('referrers are turned into friendly names', function (string $referrer, string $source) {
    expect(Visit::sourceFrom($referrer, 'homestead-realty.test'))->toBe($source);
})->with([
    ['https://l.facebook.com/l.php?u=x', 'Facebook'],
    ['https://t.co/abc', 'X (Twitter)'],
    ['https://homestead-realty.test/search', 'Direct or bookmark'],
    ['', 'Direct or bookmark'],
    ['https://news.example.org/story', 'news.example.org'],
]);

test('the reports page shows totals, sources, agents and top homes', function () {
    $admin = User::factory()->admin()->create();
    $agent = Agent::factory()->create(['name' => 'Report Agent']);
    $home = Listing::factory()->for($agent)->create(['views' => 120, 'address' => '77 Popular Way']);
    Enquiry::factory()->count(2)->create(['listing_id' => $home->id, 'agent_id' => $agent->id]);
    Enquiry::factory()->create(['agent_id' => $agent->id, 'status' => EnquiryStatus::Closed]);
    Visit::factory()->create(['source' => 'Facebook', 'views' => 9]);

    Livewire::actingAs($admin)->test('pages::dashboard.reports')
        ->assertSee('Report Agent')
        ->assertSee('77 Popular Way')
        ->assertSee('Facebook')
        ->assertSeeHtml('data-test="total-leads">3<')
        ->assertSeeHtml('data-test="total-visits">9<');
});

test('agents cannot open reports', function () {
    $agent = Agent::factory()->create();

    $this->actingAs($agent->user)->get(route('dashboard.reports'))->assertForbidden();
});
