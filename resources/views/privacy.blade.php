<x-layouts::site title="Privacy policy" description="How {{ \App\Models\Setting::get('business_name') }} collects and uses your personal information.">
    <div class="page-head">
        <div class="container">
            <h1>Privacy policy</h1>
            <p>Last updated {{ \App\Models\Setting::query()->where('key', 'privacy_policy')->first()?->updated_at?->format('F j, Y') ?? now()->format('F Y') }}</p>
        </div>
    </div>
    <section class="section">
        <div class="container">
            <div class="prose">
                {!! Str::markdown(\App\Models\Setting::privacyPolicy(), ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
            </div>
        </div>
    </section>
</x-layouts::site>
