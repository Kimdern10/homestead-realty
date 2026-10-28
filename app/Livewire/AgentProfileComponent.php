<?php

namespace App\Livewire;

use App\Models\Agent;
use App\Support\ImageOptimizer;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The public profile fields of an agent, shared by the admin's agent form
 * and the page where agents edit their own profile (both extend this class).
 */
abstract class AgentProfileComponent extends Component
{
    public string $name = '';

    public string $title = '';

    public string $phone = '';

    public string $contact_email = '';

    public string $license_number = '';

    public string $years_experience = '';

    public string $languages = '';

    public string $bio = '';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public bool $removePhoto = false;

    public ?string $currentPhotoUrl = null;

    /**
     * Copy an agent's saved profile into the form.
     */
    protected function fillProfile(Agent $agent): void
    {
        $this->name = $agent->name;
        $this->title = (string) $agent->title;
        $this->phone = (string) $agent->phone;
        $this->contact_email = (string) $agent->email;
        $this->license_number = (string) $agent->license_number;
        $this->years_experience = (string) $agent->years_experience;
        $this->languages = (string) $agent->languages;
        $this->bio = (string) $agent->bio;
        $this->currentPhotoUrl = $agent->photoUrl();
    }

    /**
     * Rules for the profile fields.
     *
     * @return array<string, mixed>
     */
    protected function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'license_number' => ['nullable', 'string', 'max:60'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            'languages' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * Messages for the profile fields.
     *
     * @return array<string, string>
     */
    protected function profileMessages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid phone number, e.g. (512) 555-0142.',
        ];
    }

    /**
     * The profile values ready to save, with a new photo stored and an old one removed.
     *
     * @return array<string, mixed>
     */
    protected function profileAttributes(Agent $agent, ImageOptimizer $images): array
    {
        $photoPath = $agent->photo_path;

        if ($this->photo || $this->removePhoto) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            $photoPath = $this->photo ? $images->storePortrait($this->photo) : null;
        }

        return [
            'name' => trim($this->name),
            'title' => $this->title ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->contact_email ? strtolower($this->contact_email) : null,
            'license_number' => $this->license_number ?: null,
            'years_experience' => $this->years_experience !== '' ? (int) $this->years_experience : null,
            'languages' => $this->languages ?: null,
            'bio' => $this->bio ?: null,
            'photo_path' => $photoPath,
        ];
    }
}
