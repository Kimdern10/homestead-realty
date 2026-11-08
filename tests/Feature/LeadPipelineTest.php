<?php

use App\Enums\EnquiryStatus;
use App\Models\Agent;
use App\Models\Enquiry;
use App\Models\Listing;
use Livewire\Livewire;

test('an agent can move a lead through the stages, add notes and set a follow-up', function () {
    $agent = Agent::factory()->create();
    $enquiry = Enquiry::factory()->create(['listing_id' => Listing::factory()->for($agent), 'agent_id' => $agent->id]);

    Livewire::actingAs($agent->user)->test('pages::dashboard.enquiries.show', ['enquiry' => $enquiry])
        ->call('setStatus', 'toured')
        ->set('note', 'Loved the kitchen, wants to bring parents.')
        ->call('addNote')
        ->assertSee('Loved the kitchen')
        ->call('followUpIn', 3)
        ->assertHasNoErrors();

    $enquiry->refresh();

    expect($enquiry->status)->toBe(EnquiryStatus::Toured)
        ->and($enquiry->follow_up_on->toDateString())->toBe(today()->addDays(3)->toDateString())
        ->and($enquiry->notes()->sole()->user_id)->toBe($agent->user_id);
});

test('the board groups leads by stage and moves one forward', function () {
    $agent = Agent::factory()->create();
    $enquiry = Enquiry::factory()->create(['agent_id' => $agent->id, 'status' => EnquiryStatus::Contacted, 'name' => 'Board Person']);

    Livewire::actingAs($agent->user)->test('pages::dashboard.enquiries.index')
        ->set('view', 'board')
        ->assertSee('Board Person')
        ->call('advance', $enquiry->id);

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Toured);
});

test('an agent cannot see or move another agent\'s lead', function () {
    $agent = Agent::factory()->create();
    $other = Enquiry::factory()->create(['name' => 'Not Yours']);

    Livewire::actingAs($agent->user)->test('pages::dashboard.enquiries.index')
        ->set('view', 'board')
        ->assertDontSee('Not Yours');

    Livewire::actingAs($agent->user)->test('pages::dashboard.enquiries.index')
        ->call('advance', $other->id)
        ->assertNotFound();

    expect($other->fresh()->status)->toBe(EnquiryStatus::New);
});

test('the due filter shows only open leads whose follow-up has arrived', function () {
    $agent = Agent::factory()->create();
    Enquiry::factory()->create(['agent_id' => $agent->id, 'name' => 'Due Today', 'follow_up_on' => today()]);
    Enquiry::factory()->create(['agent_id' => $agent->id, 'name' => 'Due Later', 'follow_up_on' => today()->addWeek()]);

    Livewire::actingAs($agent->user)->test('pages::dashboard.enquiries.index')
        ->set('due', true)
        ->assertSee('Due Today')
        ->assertDontSee('Due Later');
});
