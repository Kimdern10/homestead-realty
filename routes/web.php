<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::livewire('search', 'pages::search')->name('search');
Route::get('homes/{listing}', [ListingController::class, 'show'])->name('listings.show');
Route::get('cities', [CityController::class, 'index'])->name('cities.index');
Route::get('cities/{city}', [CityController::class, 'show'])->name('cities.show');
Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
Route::get('agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
Route::view('privacy', 'privacy')->name('privacy');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::post('favorites/{listing}', [FavoriteController::class, 'toggle'])
    ->middleware(['auth', 'verified', 'throttle:60,1'])
    ->name('favorites.toggle');

Route::livewire('saved', 'pages::saved')->middleware(['auth', 'verified'])->name('saved');

require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
