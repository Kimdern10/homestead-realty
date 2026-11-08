@foreach ($homes as $home)
    <x-listing-card :listing="$home" />
@endforeach
