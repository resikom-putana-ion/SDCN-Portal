@props(['person', 'variant' => 'member', 'label' => null])
<button
    type="button"
    {{ $attributes->class(['organization-person', 'is-'.$variant]) }}
    data-organization-person
    data-organization-name="{{ $person['name'] }}"
    data-organization-role="{{ $person['role'] }}"
    aria-pressed="false"
    aria-label="Tampilkan foto {{ $person['name'] }}, {{ $person['role'] }}"
>
    <span class="organization-person-photo">
        <span class="organization-photo-placeholder" aria-hidden="true"><b>↑</b><small>Lihat foto</small></span>
        <x-landing-preview.staff-portrait :index="$person['portrait']" :name="$person['name']" :photo="$person['photo']"/>
    </span>
    <span class="organization-person-copy">
        @if($label)<span class="organization-person-label">{{ $label }}</span>@endif
        <strong>{{ $person['name'] }}</strong>
        <small>{{ $person['role'] }}</small>
    </span>
    <span class="organization-person-action" aria-hidden="true"><b>↑</b><small>Foto</small></span>
</button>
