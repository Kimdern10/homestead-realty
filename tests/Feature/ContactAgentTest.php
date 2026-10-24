<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('contact-agent:127.0.0.1');
});

test('a visitor can message the agent and the agent and office are emailed', function () {
    Setting::put(['email' => 'office@example.com']);
    $listing = Listing::factory()->create();

    Livewire::test('contact-agent', ['agent' => $listing->agent, 'listing' => $listing])
        ->call('setMode', 'message')
        ->set('name', 'Ashley Nguyen')
        ->set('email', 'Ashley@Example.com')
        ->set('message', 'Is it still available?')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee('Message sent!');

    $enquiry = Enquiry::query()->sole();

    expect($enquiry->type)->toBe(EnquiryType::Message)
        ->and($enquiry->status)->toBe(EnquiryStatus::New)
        ->and($enquiry->listing_id)->toBe($listing->id)
        ->and($enquiry->agent_id)->toBe($listing->agent_id)
        ->and($enquiry->email)->toBe('ashley@example.com');

    Mail::assertSent(EnquiryReceived::class, fn (EnquiryReceived $mail) => $mail->hasTo($listing->agent->contactEmail()) && $mail->hasCc('office@example.com') && $mail->hasReplyTo('ashley@example.com'));
});

test('a visitor can request a tour on one of the next days', function () {
    $listing = Listing::factory()->create();
    $day = today()->addDays(3)->toDateString();

    Livewire::test('contact-agent', ['agent' => $listing->agent, 'listing' => $listing])
        ->assertSet('mode', 'tour')
        ->set('name', 'Brian Walker')
        ->set('email', 'brian@example.com')
        ->set('phone', '(305) 555-0131')
        ->set('tour_date', $day)
        ->set('tour_time', 'afternoon')
        ->set('tour_kind', 'video')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Tour request sent!');

    $enquiry = Enquiry::query()->sole();

    expect($enquiry->type)->toBe(EnquiryType::Tour)
        ->and($enquiry->tour_date->toDateString())->toBe($day)
        ->and($enquiry->tour_time)->toBe('afternoon')
        ->and($enquiry->tour_kind)->toBe('video');
});

test('a tour needs a phone number and a day from the list', function () {
    $listing = Listing::factory()->create();

    Livewire::test('contact-agent', ['agent' => $listing->agent, 'listing' => $listing])
        ->set('name', 'Brian Walker')
        ->set('email', 'brian@example.com')
        ->set('phone', '')
        ->set('tour_date', today()->subDay()->toDateString())
        ->call('submit')
        ->assertHasErrors(['phone' => 'required', 'tour_date' => 'in']);

    expect(Enquiry::query()->count())->toBe(0);
});

test('a message needs a name, a valid email and some text', function () {
    $listing = Listing::factory()->create();

    Livewire::test('contact-agent', ['agent' => $listing->agent, 'listing' => $listing])
        ->call('setMode', 'message')
        ->set('message', '')
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors(['name' => 'required', 'email' => 'email', 'message' => 'required']);
});

test('tours cannot be booked where tours are switched off', function () {
    $listing = Listing::factory()->create();

    Livewire::test('contact-agent', ['agent' => $listing->agent, 'allowTours' => false])
        ->assertSet('mode', 'message')
        ->call('setMode', 'tour')
        ->assertSet('mode', 'message')
        ->assertDontSee('Schedule a tour');
});

test('spam bots that fill the hidden field are ignored', function () {
    $listing = Listing::factory()->create();

    Livewire::test('contact-agent', ['agent' => $listing->agent, 'listing' => $listing])
        ->call('setMode', 'message')
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'Buy cheap stuff')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertSet('sent', true);

    expect(Enquiry::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('a visitor can only send a few messages in ten minutes', function () {
    $listing = Listing::factory()->create();

    $component = Livewire::test('contact-agent', ['agent' => $listing->agent, 'listing' => $listing])
        ->call('setMode', 'message')
        ->set('name', 'Ashley')
        ->set('email', 'ashley@example.com')
        ->set('message', 'Hello');

    foreach (range(1, 5) as $attempt) {
        $component->call('another')->call('submit')->assertHasNoErrors();
    }

    $component->call('another')->call('submit')->assertHasErrors('form');

    expect(Enquiry::query()->count())->toBe(5);
});
