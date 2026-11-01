<?php

use Illuminate\Support\Facades\Route;

// Short addresses people try first.
Route::redirect('admin', 'dashboard');
Route::redirect('admin/listings/create', '/dashboard/listings/create');

Route::middleware(['auth', 'staff', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('/', 'pages::dashboard.index')->name('dashboard');

    Route::livewire('listings', 'pages::dashboard.listings.index')->name('dashboard.listings.index');
    Route::livewire('listings/create', 'pages::dashboard.listings.form')->name('dashboard.listings.create');
    Route::livewire('listings/{listing:id}/edit', 'pages::dashboard.listings.form')->name('dashboard.listings.edit');

    Route::livewire('enquiries', 'pages::dashboard.enquiries.index')->name('dashboard.enquiries.index');
    Route::livewire('enquiries/{enquiry}', 'pages::dashboard.enquiries.show')->name('dashboard.enquiries.show');

    Route::livewire('profile', 'pages::dashboard.agent-profile')->name('dashboard.profile');

    Route::livewire('applications', 'pages::dashboard.applications.index')->name('dashboard.applications.index');
    Route::livewire('applications/{application}', 'pages::dashboard.applications.show')->name('dashboard.applications.show');
    Route::livewire('payments', 'pages::dashboard.payments')->name('dashboard.payments');

    Route::middleware('admin')->group(function () {
        Route::livewire('agents', 'pages::dashboard.agents.index')->name('dashboard.agents.index');
        Route::livewire('agents/create', 'pages::dashboard.agents.form')->name('dashboard.agents.create');
        Route::livewire('agents/{agent:id}/edit', 'pages::dashboard.agents.form')->name('dashboard.agents.edit');

        Route::livewire('cities', 'pages::dashboard.cities.index')->name('dashboard.cities.index');
        Route::livewire('cities/create', 'pages::dashboard.cities.form')->name('dashboard.cities.create');
        Route::livewire('cities/{city:id}/edit', 'pages::dashboard.cities.form')->name('dashboard.cities.edit');

        Route::livewire('settings', 'pages::dashboard.settings')->name('dashboard.settings');
        Route::livewire('import', 'pages::dashboard.import')->name('dashboard.import');
        Route::livewire('bookings', 'pages::dashboard.bookings')->name('dashboard.bookings');
        Route::livewire('payment-setup', 'pages::dashboard.payment-setup')->name('dashboard.payment-setup');
        Route::livewire('services', 'pages::dashboard.services')->name('dashboard.services');
        Route::livewire('home-types', 'pages::dashboard.home-types')->name('dashboard.home-types');
        Route::livewire('amenities', 'pages::dashboard.amenities')->name('dashboard.amenities');
    });
});
