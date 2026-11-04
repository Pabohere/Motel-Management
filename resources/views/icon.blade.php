<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('close')<path d="m6 6 12 12M6 18 18 6"/>@break
@case('key')<circle cx="8" cy="9" r="5"/><path d="m12 13 8 8 2-2-3-3 2-2-3-3"/>@break
@case('logout')<path d="M10 4H4v16h6m4-13 5 5-5 5m-6-5h11"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>@break
@case('bed')<path d="M3 18v3m18-3v3M3 18h18V9a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9zm0-5h18M7 10h3m4 0h3"/>@break
@case('broom')<path d="m15 3-5 9m-2-1 7 4-4 7-8-5 5-6zm-2 5 6 4m-3-5-3 5"/>@break
@case('door')<path d="M4 21h16M6 21V4l10-2v19M16 4h3v17M12 12h.01"/>@break
@case('person')<circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2z"/>@break
@case('person-plus')<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a7 7 0 0 1 12-5m5-3v8m-4-4h8"/>@break
@case('archive')<rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v13h14V8m-10 4h6"/>@break
@case('home')<path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM9 21v-8h6v8"/>@break
@case('users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18m-12 5h.01m4 0h.01m4 0h.01"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
@case('wallet')<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18m-4 4h4v4h-4a2 2 0 0 1 0-4"/>@break
@case('search')<circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5 5"/>@break
@case('filter')<path d="M5 3v4m0 4v10M12 3v10m0 4v4M19 3v2m0 4v12"/><circle cx="5" cy="9" r="2"/><circle cx="12" cy="15" r="2"/><circle cx="19" cy="7" r="2"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('arrow')<path d="M7 17 17 7M7 7h10v10"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@case('tool')<path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18a2.1 2.1 0 0 0 3 3l5.3-5.3a5 5 0 0 0 6.4-6.4L14 12l-2-2z"/>@break
@default<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 11v5"/>
@endswitch
</svg>