<?php

use App\Models\City;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

test('the admin can write a guide for a city', function () {
    $admin = User::factory()->admin()->create();
    $city = City::factory()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.guides.form')
        ->set('title', 'Best neighborhoods for families')
        ->set('city_id', (string) $city->id)
        ->set('body', "## Schools\n\nGreat schools nearby.\n\n- Parks\n- Libraries")
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard.guides.index'));

    $post = Post::query()->sole();

    expect($post->slug)->toBe('best-neighborhoods-for-families')
        ->and($post->city_id)->toBe($city->id)
        ->and($post->is_published)->toBeTrue();
});

test('published guides show on the guides page and render their text', function () {
    $post = Post::factory()->create(['title' => 'Moving to Austin', 'body' => "## Getting around\n\nTake the **bus**.\n\n<script>alert(1)</script>"]);

    $this->get(route('guides.index'))->assertOk()->assertSee('Moving to Austin');

    $this->get(route('guides.show', $post))
        ->assertOk()
        ->assertSee('<h2>Getting around</h2>', false)
        ->assertSee('<strong>bus</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('drafts and future guides stay hidden', function () {
    $draft = Post::factory()->draft()->create(['title' => 'Secret draft']);
    $future = Post::factory()->create(['title' => 'Coming soon', 'published_at' => now()->addWeek()]);

    $this->get(route('guides.index'))->assertDontSee('Secret draft')->assertDontSee('Coming soon');
    $this->get(route('guides.show', $draft))->assertNotFound();
    $this->get(route('guides.show', $future))->assertNotFound();
});

test('guides are listed in the sitemap', function () {
    $post = Post::factory()->create();

    $this->get(route('sitemap'))->assertOk()->assertSee(route('guides.show', $post), false);
});
