<svg xmlns="http://www.w3.org/2000/svg" class="icon text-white" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
@if($icon === 'user-plus')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/><line x1="19" y1="7" x2="19" y2="13"/><line x1="22" y1="10" x2="16" y2="10"/>
@elseif($icon === 'login')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/><path d="M21 12h-13l3 -3m0 6l-3 -3"/>
@elseif($icon === 'shield')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"/>
@elseif($icon === 'briefcase')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7v-2a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v2"/>
@elseif($icon === 'trash')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="4" y1="7" x2="20" y2="7"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>
@elseif($icon === 'trending-up')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="3,17 9,11 13,15 21,7"/><polyline points="14,7 21,7 21,14"/>
@elseif($icon === 'trending-down')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="3,7 9,13 13,9 21,17"/><polyline points="21,10 21,17 14,17"/>
@elseif($icon === 'refresh')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
@elseif($icon === 'database')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6a8 3 0 0 0 16 0v-6"/><path d="M4 12v6a8 3 0 0 0 16 0v-6"/>
@elseif($icon === 'settings')
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/><circle cx="12" cy="12" r="3"/>
@else
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="3,17 9,11 13,15 21,7"/>
@endif
</svg>
