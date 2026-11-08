<x-mail::message>
# Confirm your email

Welcome to {{ $business }}! Enter this code on the website to finish creating your account:

<x-mail::panel>
<div style="font-size: 34px; font-weight: 800; letter-spacing: 12px; text-align: center; color: #121417;">{{ $code }}</div>
</x-mail::panel>

The code works for {{ $minutes }} minutes. Once confirmed, you can save homes and searches and get alerts when new homes match.

If you didn't create an account, you can ignore this email.
</x-mail::message>
