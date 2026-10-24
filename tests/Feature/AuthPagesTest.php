<?php

use App\Enums\UserRole;
use App\Models\User;

test('the sign-in page tells agents their login comes from the office', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('Agent accounts are created by the office admin');
});

test('the sign-up page is for buyers and renters, not agents', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your free account')
        ->assertSee('Agents don’t sign up here', escape: false);
});

test('signing up creates a visitor account, never an agent or admin', function () {
    $this->post(route('register.store'), [
        'name' => 'Jordan Taylor',
        'email' => 'jordan@example.com',
        'password' => 'a-strong-password',
        'password_confirmation' => 'a-strong-password',
        'role' => 'admin',
    ]);

    expect(User::query()->where('email', 'jordan@example.com')->sole()->fresh()->role)->toBe(UserRole::Member);
});
