<?php

use App\Enums\UserRole;
use App\Models\User;

test('the command creates the owner’s admin login', function () {
    $this->artisan('app:create-admin', ['--name' => 'Office Admin', '--email' => 'Owner@Example.com', '--password' => 'a-strong-password'])
        ->assertSuccessful();

    $user = User::query()->sole();
    expect($user->email)->toBe('owner@example.com')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('the command turns an existing user into an admin', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('app:create-admin', ['--email' => 'owner@example.com', '--password' => 'a-strong-password'])->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

test('the command rejects a short password', function () {
    $this->artisan('app:create-admin', ['--name' => 'Owner', '--email' => 'owner@example.com', '--password' => 'short'])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
