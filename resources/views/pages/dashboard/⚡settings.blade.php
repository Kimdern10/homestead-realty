<?php

use App\Models\Setting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Business details')] class extends Component {
    /** @var array<string, string> */
    public array $values = [];

    /**
     * Load the saved values.
     */
    public function mount(): void
    {
        $this->values = Setting::values();
    }

    /**
     * Get the validation rules for the form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'values.business_name' => ['required', 'string', 'max:80'],
            'values.tagline' => ['nullable', 'string', 'max:160'],
            'values.phone' => ['nullable', 'string', 'max:30'],
            'values.email' => ['nullable', 'email', 'max:150'],
            'values.address' => ['nullable', 'string', 'max:200'],
            'values.office_hours' => ['nullable', 'string', 'max:100'],
            'values.broker_license' => ['nullable', 'string', 'max:60'],
            'values.facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'values.instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'values.x_url' => ['nullable', 'url:http,https', 'max:255'],
            'values.linkedin_url' => ['nullable', 'url:http,https', 'max:255'],
            'values.youtube_url' => ['nullable', 'url:http,https', 'max:255'],
            'values.hero_title' => ['required', 'string', 'max:80'],
            'values.hero_text' => ['nullable', 'string', 'max:250'],
            'values.seo_title' => ['required', 'string', 'max:70'],
            'values.seo_description' => ['required', 'string', 'max:300'],
            'values.mortgage_rate' => ['required', 'numeric', 'min:0', 'max:25'],
            'values.privacy_policy' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * Get the custom validation attribute names.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return collect(array_keys(Setting::DEFAULTS))->mapWithKeys(fn (string $key) => ["values.{$key}" => str_replace('_', ' ', $key)])->all();
    }

    /**
     * Save every value.
     */
    public function save(): void
    {
        $validated = $this->validate()['values'];

        Setting::put(array_map(fn ($value) => trim((string) $value), $validated));

        Flux::toast(__('Business details saved.'), variant: 'success');
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Business details') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Shown in the website header, footer, emails and Google results.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-8">
        <flux:fieldset>
            <flux:legend>{{ __('Your business') }}</flux:legend>
            <div class="grid gap-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="values.business_name" :label="__('Business name *')" />
                    <flux:input wire:model="values.broker_license" :label="__('Broker license number')" :description="__('Shown in the footer, as many states require.')" />
                </div>
                <flux:input wire:model="values.tagline" :label="__('Tagline')" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="values.phone" type="tel" icon="phone" :label="__('Phone')" />
                    <flux:input wire:model="values.email" type="email" icon="envelope" :label="__('Office email')" :description="__('Gets a copy of every enquiry.')" />
                </div>
                <flux:input wire:model="values.address" icon="map-pin" :label="__('Office address')" />
                <flux:input wire:model="values.office_hours" icon="clock" :label="__('Office hours')" />
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Home page and Google') }}</flux:legend>
            <div class="grid gap-5">
                <flux:input wire:model="values.hero_title" :label="__('Main heading *')" />
                <flux:textarea wire:model="values.hero_text" rows="2" :label="__('Text under the heading')" />
                <flux:input wire:model="values.seo_title" :label="__('Google title *')" :description="__('Shown after your business name in the browser tab and Google.')" />
                <flux:textarea wire:model="values.seo_description" rows="2" :label="__('Google description *')" :description="__('About 150 characters.')" />
                <flux:input wire:model="values.mortgage_rate" type="number" step="0.01" min="0" icon:trailing="percent-badge" :label="__('Mortgage rate used in the calculator *')" class="max-w-60" />
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Social media') }}</flux:legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="values.facebook_url" type="url" label="Facebook" placeholder="https://facebook.com/…" />
                <flux:input wire:model="values.instagram_url" type="url" label="Instagram" placeholder="https://instagram.com/…" />
                <flux:input wire:model="values.x_url" type="url" label="X" placeholder="https://x.com/…" />
                <flux:input wire:model="values.linkedin_url" type="url" label="LinkedIn" placeholder="https://linkedin.com/…" />
                <flux:input wire:model="values.youtube_url" type="url" label="YouTube" placeholder="https://youtube.com/…" />
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Privacy policy') }}</flux:legend>
            <flux:textarea wire:model="values.privacy_policy" rows="10" :label="__('Your own privacy policy (optional)')" :description="__('Leave empty to use the starter policy, filled in with your details. Use ## for headings and - for lists.')" />
        </flux:fieldset>

        <div>
            <flux:button variant="primary" type="submit">{{ __('Save details') }}</flux:button>
        </div>
    </form>
</div>
