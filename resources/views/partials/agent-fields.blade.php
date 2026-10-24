{{-- Profile fields shared by the admin's agent form and the agent's own profile page. --}}
<flux:fieldset>
    <flux:legend>{{ __('Public profile') }}</flux:legend>
    <div class="grid gap-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Full name *')" />
            <flux:input wire:model="title" :label="__('Job title')" :placeholder="__('e.g. Realtor®, Broker Associate')" />
            <flux:input wire:model="phone" type="tel" icon="phone" :label="__('Phone')" placeholder="(512) 555-0142" />
            <flux:input wire:model="contact_email" type="email" icon="envelope" :label="__('Email for enquiries')" :description="__('Leave empty to use the login email.')" />
            <flux:input wire:model="license_number" :label="__('License number')" />
            <flux:input wire:model="years_experience" type="number" min="0" :label="__('Years of experience')" />
        </div>
        <flux:input wire:model="languages" :label="__('Languages')" :placeholder="__('e.g. English and Spanish')" />
        <flux:textarea wire:model="bio" rows="6" :label="__('About')" :description="__('A few friendly sentences shown on the profile. Leave a blank line between paragraphs.')" />

        <div class="grid gap-3">
            <flux:input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" :label="__('Photo')" :description="__('A clear, square-ish headshot works best.')" />
            <div wire:loading wire:target="photo"><flux:text>{{ __('Uploading…') }}</flux:text></div>
            @if ($photo && $photo->isPreviewable() && ! $errors->has('photo'))
                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-24 rounded-full border-2 border-amber-400 object-cover">
            @elseif ($currentPhotoUrl && ! $removePhoto)
                <div class="flex items-center gap-3">
                    <img src="{{ $currentPhotoUrl }}" alt="" class="size-24 rounded-full object-cover">
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="$set('removePhoto', true)">{{ __('Remove photo') }}</flux:button>
                </div>
            @elseif ($removePhoto)
                <flux:text>{{ __('The photo will be removed when you save.') }} <flux:link class="cursor-pointer" wire:click="$set('removePhoto', false)">{{ __('Undo') }}</flux:link></flux:text>
            @endif
        </div>
    </div>
</flux:fieldset>
