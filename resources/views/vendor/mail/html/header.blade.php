@props(['url'])
@php($businessName = \App\Models\Setting::get('business_name') ?: config('app.name'))
<tr>
<td class="header">
<a href="{{ $url }}" class="brand" style="display: inline-block; text-decoration: none;">
<table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
<tr>
<td style="padding-right: 10px; vertical-align: middle;"><img src="{{ asset('images/email-logo.png') }}" class="logo" width="40" height="40" alt="{{ $businessName }}"></td>
<td class="brand-name" style="vertical-align: middle;">{{ $businessName }}</td>
</tr>
</table>
</a>
</td>
</tr>
