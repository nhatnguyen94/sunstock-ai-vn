@use('App\Support\SiteSettings')
{{-- Site-wide notice an admin can switch on (Admin > Giao diện & Cache): maintenance, an incident, important news. The text is escaped;
     the link only ever points to this site or to an https address (checked when it is saved). A visitor can close it; it comes back when the text changes. --}}
@php $notice = SiteSettings::activeAnnouncement(); @endphp
@if($notice)
<style>
    .site-notice { position: relative; display: flex; align-items: center; justify-content: center; gap: .6rem; padding: .55rem 2.6rem .55rem 1rem; font-size: .9rem; font-weight: 600; text-align: center; color: #fff; }
    .site-notice.info { background: #2563eb; } .site-notice.warning { background: #b45309; } .site-notice.danger { background: #b91c1c; }
    .site-notice a { color: #fff; text-decoration: underline; white-space: nowrap; }
    .site-notice button { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: #fff; font-size: 1.3rem; line-height: 1; opacity: .8; cursor: pointer; }
    .site-notice button:hover { opacity: 1; }
    .site-notice[hidden] { display: none; }
</style>
<div class="site-notice {{ $notice['level'] }}" id="siteNotice" role="status" data-id="{{ crc32($notice['level'].$notice['text'].$notice['url']) }}" hidden>
    <i class="bi bi-{{ ['info' => 'info-circle-fill', 'warning' => 'exclamation-triangle-fill', 'danger' => 'exclamation-octagon-fill'][$notice['level']] }}" aria-hidden="true"></i>
    <span>{{ $notice['text'] }}</span>
    @if($notice['url'] !== '')<a href="{{ $notice['url'] }}" @if(str_starts_with($notice['url'], 'http')) rel="noopener noreferrer" target="_blank" @endif>{{ $notice['link_text'] !== '' ? $notice['link_text'] : 'Xem thêm' }}</a>@endif
    <button type="button" aria-label="Đóng thông báo" id="siteNoticeClose">&times;</button>
</div>
<script>
(function () {
    var box = document.getElementById('siteNotice');
    if (!box) return;
    var key = 'sunstock-notice-' + box.dataset.id;
    var closed = false;
    try { closed = localStorage.getItem(key) === 'closed'; } catch (e) {}
    if (!closed) box.hidden = false;
    document.getElementById('siteNoticeClose').addEventListener('click', function () {
        box.hidden = true;
        try { localStorage.setItem(key, 'closed'); } catch (e) {}
    });
})();
</script>
@endif
