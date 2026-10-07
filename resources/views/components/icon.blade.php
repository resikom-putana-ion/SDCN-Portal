@props(['name'=>'file'])
@php
$paths=[
'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
'file'=>'<path d="M6 2h8l5 5v15H6z M14 2v6h5 M9 12h7 M9 16h7"/>',
'users'=>'<circle cx="9" cy="7" r="3"/><path d="M3 21v-4a6 6 0 0 1 12 0v4z M17 4a3 3 0 0 1 0 6 M18 13a5 5 0 0 1 3 5v3"/>',
'book'=>'<path d="M12 5C9 2 5 2 2 4v16c4-2 7-2 10 1 3-3 6-3 10-1V4c-3-2-7-2-10 1z M12 5v16"/>',
'wallet'=>'<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 8V3h15 M21 11h-6v5h6"/>',
'globe'=>'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
'bell'=>'<path d="M5 17h14l-2-3V8a5 5 0 0 0-10 0v6z M10 21h4"/>',
'chart'=>'<path d="M4 3v18h17 M8 16V9 M13 16V5 M18 16v-5"/>',
'settings'=>'<path d="m9 2 6 0 1 3 3 1 2 5-2 3v4l-4 3-3-1-3 1-4-3v-4l-2-3 2-5 3-1z"/><circle cx="12" cy="12" r="3"/>',
'search'=>'<circle cx="10" cy="10" r="7"/><path d="m15 15 7 7"/>',
'chevron'=>'<path d="m9 5 7 7-7 7"/>',
'menu'=>'<path d="M4 5h16 M4 12h16 M4 19h16"/>',
'moon'=>'<path d="M21 13A9 9 0 0 1 11 3a9 9 0 1 0 10 10z"/>',
'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6 M17 2v6 M3 10h18"/>',
'briefcase'=>'<rect x="3" y="7" width="18" height="14" rx="1"/><path d="M8 7V3h8v4 M3 12h18 M10 11v4h4v-4"/>',
'school'=>'<path d="M3 21V8l9-6 9 6v13z M9 21v-9h6v9 M7 9h1 M16 9h1"/>',
'download'=>'<path d="M12 2v14 m-5-5 5 5 5-5 M3 16v6h18v-6"/>',
'check'=>'<path d="m5 12 5 5L20 6"/>',
'close'=>'<path d="m5 5 14 14 M19 5 5 19"/>',
'plus'=>'<path d="M12 4v16 M4 12h16"/>',
'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
];
@endphp
<svg {{ $attributes->merge(['class'=>'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['file'] !!}</svg>
