<x-mail::message>
# Confirm your email

Enter this code on the {{ $business }} website to finish creating your account:

<x-mail::panel>
<div style="font-size: 32px; font-weight: 700; letter-spacing: 10px; text-align: center;">{{ $code }}</div>
</x-mail::panel>

The code works for {{ $minutes }} minutes. If you didn't create an account, you can ignore this email.

{{ $business }}
</x-mail::message>
