<svg xmlns="http://www.w3.org/2000/svg" class="icon text-{{ $color }}" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
@if($icon === 'news')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 6h3a1 1 0 0 1 1 1v11a2 2 0 0 1 -4 0v-13a1 1 0 0 0 -1 -1h-10a1 1 0 0 0 -1 1v12a3 3 0 0 0 3 3h11"/><line x1="8" y1="8" x2="12" y2="8"/><line x1="8" y1="12" x2="12" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/>
@elseif($icon === 'currency')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16.7 8a3 3 0 0 0 -2.7 -2h-4a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-4a3 3 0 0 1 -2.7 -2"/><path d="M12 3v3m0 12v3"/>
@elseif($icon === 'flame')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12c2 -2.96 0 -7 -1 -8c0 3.038 -1.773 4.741 -3 6c-1.226 1.26 -2 3.24 -2 5a6 6 0 0 0 12 0c0 -1.532 -.> 3.73 -2 -6c-1 2 -2.165 2.772 -4 3z"/>
@elseif($icon === 'trending-up')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="3,17 9,11 13,15 21,7"/><polyline points="14,7 21,7 21,14"/>
@elseif($icon === 'database')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6a8 3 0 0 0 16 0v-6"/><path d="M4 12v6a8 3 0 0 0 16 0v-6"/>
@elseif($icon === 'report')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h5.697"/><path d="M18 14v4h4"/><path d="M18 11v-4a2 2 0 0 0 -2 -2h-2"/><rect x="10" y="3" width="4" height="4" rx="2"/><circle cx="18" cy="18" r="4"/><path d="M15 3v4"/>
@elseif($icon === 'building')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="3" y1="21" x2="21" y2="21"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/><path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16"/>
@elseif($icon === 'chart-pie')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 3.2a9 9 0 1 0 10.8 10.8a1 1 0 0 0 -1 -1h-3.8a4.1 4.1 0 1 1 -5 -5v-4a.9 .9 0 0 0 -1 -.8"/><path d="M15 3.5a9 9 0 0 1 5.5 5.5h-4.5a9 9 0 0 0 -1 -1z"/>
@else
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="3,17 9,11 13,15 21,7"/>
@endif
</svg>
