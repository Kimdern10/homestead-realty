<?php

use App\Concerns\AgentProfileForm;
use App\Models\Agent;
use App\Support\ImageOptimizer;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('My agent profile')] class extends Component {
    use AgentProfileForm, WithFileUploads;

    /**
     * Only agents have a profile to edit.
     */
    public function mount(): void
    {
        $agent = auth()->user()->agent;

        abort_if($agent === null, 404);

        $this->fillProfile($agent);
    }

    /**
     * Get the validation rules for the form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return $this->profileRules();
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return $this->profileMessages();
    }

    /**
     * Save the agent's own profile.
     */
    public function save(ImageOptimizer $images): void
    {
        $this->validate();

        /** @var Agent $agent */
        $agent = auth()->user()->agent;
        $agent->update($this->profileAttributes($agent, $images));

        $this->reset('photo', 'removePhoto');
        $this->currentPhotoUrl = $agent->photoUrl();

        Flux::toast(__('Profile saved.'), variant: 'success');
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('My agent profile') }}</flux:heading>
            <flux:text class="mt-1">{{ __('This is what visitors see on your profile page and next to your listings.') }}</flux:text>
        </div>
        <flux:button size="sm" icon="arrow-top-right-on-square" :href="route('agents.show', auth()->user()->agent)" target="_blank">{{ __('View profile') }}</flux:button>
    </div>

    <form wire:submit="save" class="flex flex-col gap-8">
        @include('partials.agent-fields')

        <div>
            <flux:button variant="primary" type="submit">{{ __('Save profile') }}</flux:button>
        </div>
    </form>
</div>
