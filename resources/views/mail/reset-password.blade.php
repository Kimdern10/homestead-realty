<x-mail::message>
# Reset your password

Hi{{ $name ? ' '.Str::before($name, ' ') : '' }}, we got a request to reset the password for your {{ $business }} account.

<x-mail::button :url="$url" color="error">
Choose a new password
</x-mail::button>

This link works for {{ $minutes }} minutes. If you didn't ask to reset your password, you can ignore this email; your password stays the same.

<x-slot:subcopy>
If the button doesn't work, copy this link into your browser: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
