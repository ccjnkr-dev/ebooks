
@props(['name'=>'book'])
<svg {{ $attributes->merge(['class'=>'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('bag')<path d="M5 7h14l1 14H4L5 7Z"/><path d="M9 8V5a3 3 0 0 1 6 0v3"/>
@break
@case('user')<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
@break
@case('music')<path d="M9 18V5l11-2v13M9 8l11-2"/><ellipse cx="6" cy="18" rx="3" ry="3"/><ellipse cx="17" cy="16" rx="3" ry="3"/>
@break
@case('art')<rect x="3" y="3" width="18" height="18" rx="1"/><circle cx="8" cy="8" r="1"/><path d="m4 18 5-6 4 3 3-5 5 8"/>
@break
@case('search')<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>
@break
@case('lock')<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/>
@break
@case('play')<path d="m9 5 11 7-11 7V5Z"/>
@break
@case('menu')<path d="M3 6h18M3 12h18M3 18h18"/>
@break
@case('check')<path d="m4 12 5 5L20 6"/>
@break
@case('grid')<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
@break
@default<path d="M12 5v16M3 3c4-1 6 0 9 2 3-2 5-3 9-2v16c-4-1-6 0-9 2-3-2-5-3-9-2V3Z"/>
@endswitch</svg>
