<?php

use App\Enums\EnquiryStatus;
use App\Models\Agent;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

test('agents see only enquiries sent to them', function () {
    $agent = Agent::factory()->create();
    Enquiry::factory()->for(Listing::factory()->for($agent))->create(['name' => 'Mine Person', 'agent_id' => $agent->id]);
    Enquiry::factory()->create(['name' => 'Other Person']);

    $this->actingAs($agent->user)->get(route('dashboard.enquiries.index'))
        ->assertOk()
        ->assertSee('Mine Person')
        ->assertDontSee('Other Person');
});

test('an agent cannot open another agent’s enquiry', function () {
    $agent = Agent::factory()->create();
    $enquiry = Enquiry::factory()->create();

    $this->actingAs($agent->user)->get(route('dashboard.enquiries.show', $enquiry))->assertForbidden();
});

test('an agent can mark their enquiry as contacted', function () {
    $enquiry = Enquiry::factory()->tour()->create();

    $this->actingAs($enquiry->agent->user)->get(route('dashboard.enquiries.show', $enquiry))
        ->assertOk()
        ->assertSee('Requested tour');

    Livewire::actingAs($enquiry->agent->user)->test('pages::dashboard.enquiries.show', ['enquiry' => $enquiry])
        ->call('setStatus', 'contacted');

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Contacted);
});

test('only the admin can delete an enquiry', function () {
    $enquiry = Enquiry::factory()->create();

    Livewire::actingAs($enquiry->agent->user)->test('pages::dashboard.enquiries.show', ['enquiry' => $enquiry])
        ->call('delete')
        ->assertForbidden();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.enquiries.show', ['enquiry' => $enquiry])
        ->call('delete')
        ->assertRedirect(route('dashboard.enquiries.index'));

    expect(Enquiry::query()->count())->toBe(0);
});

test('the enquiry list can be filtered to tour requests', function () {
    Enquiry::factory()->create(['name' => 'Message Person']);
    Enquiry::factory()->tour()->create(['name' => 'Tour Person']);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.enquiries.index')
        ->set('type', 'tour')
        ->assertSee('Tour Person')
        ->assertDontSee('Message Person');
});
