<?php

use App\Concerns\AgentProfileForm;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\User;
use App\Support\ImageOptimizer;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Agent')] class extends Component {
    use AgentProfileForm, WithFileUploads;

    public ?Agent $agent = null;

    public string $login_email = '';

    public string $password = '';

    public bool $is_active = true;

    /**
     * Fill the form when editing.
     */
    public function mount(?Agent $agent = null): void
    {
        if (! $agent?->exists) {
            return;
        }

        $this->agent = $agent->load('user');
        $this->fillProfile($agent);
        $this->login_email = $agent->user->email;
        $this->is_active = $agent->is_active;
    }

    /**
     * Get the validation rules for the form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            ...$this->profileRules(),
            'login_email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->agent?->user_id)],
            'password' => $this->agent ? ['nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            ...$this->profileMessages(),
            'login_email.unique' => 'Someone already uses this email to log in.',
        ];
    }

    /**
     * Create or update the agent and their login.
     */
    public function save(ImageOptimizer $images): void
    {
        $this->validate();

        $agent = $this->agent ?? new Agent;

        DB::transaction(function () use ($agent, $images) {
            $user = $agent->user ?? new User;
            $user->forceFill(array_filter([
                'name' => trim($this->name),
                'email' => strtolower($this->login_email),
                'password' => $this->password !== '' ? $this->password : null,
                'role' => $user->isAdmin() ? UserRole::Admin : UserRole::Agent,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ], fn ($value) => $value !== null))->save();

            $agent->fill([
                ...$this->profileAttributes($agent, $images),
                'user_id' => $user->id,
                'slug' => $agent->slug ?? $this->uniqueSlug(),
                'is_active' => $this->is_active,
            ])->save();
        });

        Flux::toast($this->agent ? __('Agent saved.') : __('Agent added. Share the login email and password with them.'), variant: 'success');

        $this->redirectRoute('dashboard.agents.index', navigate: true);
    }

    /**
     * A web address for the agent's profile, e.g. "sarah-mitchell".
     */
    private function uniqueSlug(): string
    {
        $base = Str::slug(Str::ascii($this->name)) ?: 'agent';
        $slug = $base;
        $counter = 2;

        while (Agent::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:link :href="route('dashboard.agents.index')" wire:navigate class="text-sm">← {{ __('All agents') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ $agent ? __('Edit :name', ['name' => $agent->name]) : __('Add an agent') }}</flux:heading>
        <flux:text class="mt-1">{{ __('The agent logs in with the email and password below to add their own listings and answer enquiries.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-8">
        <flux:fieldset>
            <flux:legend>{{ __('Login') }}</flux:legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="login_email" type="email" :label="__('Login email *')" autocomplete="off" />
                <flux:input wire:model="password" type="password" viewable :label="$agent ? __('New password') : __('Password *')" :description="$agent ? __('Leave empty to keep the current password.') : __('At least 8 characters. The agent can change it later.')" autocomplete="new-password" />
            </div>
        </flux:fieldset>

        @include('partials.agent-fields')

        <flux:switch wire:model="is_active" :label="__('Active')" :description="__('Inactive agents cannot log in, and their profile and listings are hidden from the website.')" />

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" data-test="save-agent">{{ __('Save agent') }}</flux:button>
            <flux:button :href="route('dashboard.agents.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
