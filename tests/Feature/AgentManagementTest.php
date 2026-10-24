<?php

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the admin can add an agent with a login', function () {
    Storage::fake('public');

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.agents.form')
        ->set('name', 'Elena Rodríguez')
        ->set('login_email', 'Elena@Example.com')
        ->set('password', 'a-strong-password')
        ->set('title', 'Realtor®')
        ->set('phone', '(305) 555-0142')
        ->set('photo', UploadedFile::fake()->image('elena.jpg', 900, 900))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard.agents.index'));

    $agent = Agent::query()->with('user')->sole();

    expect($agent->slug)->toBe('elena-rodriguez')
        ->and($agent->user->email)->toBe('elena@example.com')
        ->and($agent->user->role)->toBe(UserRole::Agent)
        ->and(Hash::check('a-strong-password', $agent->user->password))->toBeTrue()
        ->and($agent->user->hasVerifiedEmail())->toBeTrue();
    Storage::disk('public')->assertExists($agent->photo_path);
});

test('a login email can only be used once and a new agent needs a password', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.agents.form')
        ->set('name', 'New Agent')
        ->set('login_email', 'taken@example.com')
        ->call('save')
        ->assertHasErrors(['login_email' => 'unique', 'password' => 'required']);
});

test('editing an agent keeps the password when left empty', function () {
    $agent = Agent::factory()->create();
    $hash = $agent->user->password;

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.agents.form', ['agent' => $agent])
        ->set('title', 'Broker')
        ->call('save')
        ->assertHasNoErrors();

    expect($agent->fresh()->title)->toBe('Broker')
        ->and($agent->user->fresh()->password)->toBe($hash);
});

test('the admin can deactivate an agent', function () {
    $agent = Agent::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::dashboard.agents.index')->call('toggleActive', $agent->id);

    expect($agent->fresh()->is_active)->toBeFalse();
});

test('an agent can update their own public profile', function () {
    $agent = Agent::factory()->create();

    Livewire::actingAs($agent->user)->test('pages::dashboard.agent-profile')
        ->set('bio', 'I love helping first-time buyers.')
        ->set('phone', 'not a phone')
        ->call('save')
        ->assertHasErrors(['phone'])
        ->set('phone', '(512) 555-0100')
        ->call('save')
        ->assertHasNoErrors();

    expect($agent->fresh()->bio)->toBe('I love helping first-time buyers.')
        ->and($agent->fresh()->phone)->toBe('(512) 555-0100');
});
