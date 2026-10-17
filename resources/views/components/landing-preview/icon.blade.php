@props(['name'=>'book'])
<span {{ $attributes->class(['icon']) }}><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('heart')<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>@break
@case('users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2h14ZM16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.9"/><circle cx="9" cy="7" r="4"/>@break
@case('shield')<path d="m12 2 8 4v6c0 5-8 10-8 10S4 17 4 12V6l8-4Z"/><path d="m8 12 3 3 5-6"/>@break
@case('spark')<path d="m12 2 2.6 7.4L22 12l-7.4 2.6L12 22l-2.6-7.4L2 12l7.4-2.6L12 2Z"/>@break
@case('mail')<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 6 10 7L22 6"/>@break
@case('pin')<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>@break
@case('phone')<path d="M22 16v3a2 2 0 0 1-2 2A18 18 0 0 1 3 4a2 2 0 0 1 2-2h3l2 5-3 2a14 14 0 0 0 8 8l2-3 5 2Z"/>@break
@case('home')<path d="m2 7 10-5 10 5v15H2V7Z M8 22V12h8v10M8 7h8"/>@break
@default<path d="M12 4C9 2 5 2 2 3v17c3-1 7-1 10 1 3-2 7-2 10-1V3c-3-1-7-1-10 1v17Z"/>
@endswitch
</svg></span>
