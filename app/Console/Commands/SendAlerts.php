<?php

namespace App\Console\Commands;

use App\Mail\PriceDropAlert;
use App\Mail\SavedSearchAlert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

#[Signature('alerts:send')]
#[Description('Email visitors new homes that match their saved searches, and price drops on homes they saved')]
class SendAlerts extends Command
{
    /**
     * Most homes listed in one email.
     */
    public const int MAX_HOMES = 8;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $searches = $this->sendSearchAlerts();
        $drops = $this->sendPriceDrops();

        $this->components->info("Sent {$searches} saved-search ".str('email')->plural($searches)." and {$drops} price-drop ".str('email')->plural($drops).'.');

        return self::SUCCESS;
    }

    /**
     * New homes since the last email, for each saved search with alerts on.
     */
    private function sendSearchAlerts(): int
    {
        $sent = 0;

        SavedSearch::query()
            ->where('alerts_enabled', true)
            ->whereHas('user', fn ($user) => $user->whereNotNull('email_verified_at'))
            ->with('user')
            ->chunkById(100, function ($searches) use (&$sent): void {
                foreach ($searches as $search) {
                    $since = $search->last_alerted_at ?? $search->created_at ?? now()->subDay();

                    $query = Listing::query()->published()->forCards()
                        ->filter($search->filters)
                        ->where('created_at', '>', $since);

                    $total = (clone $query)->count();
                    $homes = $query->latest()->take(self::MAX_HOMES)->get();

                    $search->forceFill(['last_alerted_at' => now()])->save();

                    if ($homes->isEmpty()) {
                        continue;
                    }

                    try {
                        Mail::to($search->user)->send(new SavedSearchAlert($search, $homes, $total));
                        $sent++;
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            });

        return $sent;
    }

    /**
     * Saved homes whose price is now lower than when we last told the visitor.
     */
    private function sendPriceDrops(): int
    {
        $rows = DB::table('favorites')
            ->join('listings', 'listings.id', '=', 'favorites.listing_id')
            ->where('listings.is_published', true)
            ->whereNotNull('favorites.notified_price')
            ->whereColumn('listings.price', '<', 'favorites.notified_price')
            ->get(['favorites.user_id', 'favorites.listing_id', 'favorites.notified_price']);

        $sent = 0;

        foreach ($rows->groupBy('user_id') as $userId => $userRows) {
            $user = User::query()->whereKey($userId)->whereNotNull('email_verified_at')->first();
            $homes = Listing::query()->published()->forCards()->whereKey($userRows->pluck('listing_id'))->get();

            if ($user !== null && $homes->isNotEmpty()) {
                $oldPrices = $userRows->pluck('notified_price', 'listing_id')->map(fn ($price) => (int) $price)->all();

                try {
                    Mail::to($user)->send(new PriceDropAlert($user, $homes, $oldPrices));
                    $sent++;
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            // Only tell them once per drop.
            foreach ($homes as $home) {
                DB::table('favorites')->where('user_id', $userId)->where('listing_id', $home->id)->update(['notified_price' => $home->price]);
            }
        }

        // When a price goes up, move the baseline up so the next cut is measured from there.
        DB::table('favorites')
            ->join('listings', 'listings.id', '=', 'favorites.listing_id')
            ->whereColumn('listings.price', '>', 'favorites.notified_price')
            ->get(['favorites.user_id', 'favorites.listing_id', 'listings.price'])
            ->each(fn ($row) => DB::table('favorites')
                ->where('user_id', $row->user_id)
                ->where('listing_id', $row->listing_id)
                ->update(['notified_price' => $row->price]));

        return $sent;
    }
}
