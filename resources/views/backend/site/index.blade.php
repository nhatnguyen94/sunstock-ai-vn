@extends('layouts.admin')

@section('title', 'Giao diện & Cache')
@section('page_pretitle', 'Nội dung & dữ liệu')
@section('page_title', 'Giao diện & Cache')

@section('breadcrumbs')
    <i class="ti ti-chevron-right"></i><span class="current">Giao diện &amp; Cache</span>
@endsection

@section('page_actions')
    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="ti ti-external-link me-1"></i> Xem trang chủ</a>
@endsection

@section('content')
<div class="row row-deck row-cards g-3 mb-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-layout-dashboard me-2 text-primary"></i>Khối hiển thị ở trang chủ</h3></div>
            <form method="POST" action="{{ route('admin.site.blocks') }}">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <p class="text-secondary small">Bỏ chọn một khối là ẩn nó khỏi trang chủ (khối ẩn cũng không còn được tính toán). Dữ liệu vẫn được đồng bộ bình thường.</p>
                    <div class="row g-2">
                        @foreach($labels as $key => $label)
                            <div class="col-sm-6">
                                <label class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="blocks[{{ $key }}]" value="1" @checked($blocks[$key])>
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Lưu</button></div>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-speakerphone me-2 text-primary"></i>Thông báo toàn trang</h3></div>
            <form method="POST" action="{{ route('admin.site.announcement') }}">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <label class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1" @checked(old('enabled', $announcement['enabled']))>
                        <span class="form-check-label"><strong>Hiển thị thông báo</strong><br><span class="text-secondary small">Một thanh trên cùng của mọi trang công khai (bảo trì, sự cố, tin quan trọng). Người xem có thể đóng; thanh hiện lại khi nội dung đổi.</span></span>
                    </label>
                    <div class="mb-3">
                        <label class="form-label" for="noticeLevel">Mức độ</label>
                        <select id="noticeLevel" name="level" class="form-select">
                            @foreach($levels as $value => $label)<option value="{{ $value }}" @selected(old('level', $announcement['level']) === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="noticeText">Nội dung</label>
                        <textarea id="noticeText" name="text" rows="2" maxlength="300" class="form-control @error('text') is-invalid @enderror">{{ old('text', $announcement['text']) }}</textarea>
                        @error('text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-7">
                            <label class="form-label" for="noticeUrl">Liên kết (không bắt buộc)</label>
                            <input id="noticeUrl" name="url" class="form-control @error('url') is-invalid @enderror" value="{{ old('url', $announcement['url']) }}" placeholder="https://… hoặc /duong-dan">
                            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="noticeLinkText">Chữ của liên kết</label>
                            <input id="noticeLinkText" name="link_text" maxlength="40" class="form-control" value="{{ old('link_text', $announcement['link_text']) }}" placeholder="Xem thêm">
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Lưu thông báo</button></div>
            </form>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-star me-2 text-primary"></i>Cổ phiếu nổi bật ở trang chủ</h3></div>
    <form method="POST" action="{{ route('admin.site.featured') }}">
        @csrf
        @method('PUT')
        <div class="card-body">
            <label class="form-label" for="featuredSymbols">Mã cổ phiếu (tối đa {{ $maxFeatured }}, cách nhau bằng dấu phẩy)</label>
            <input id="featuredSymbols" name="symbols" class="form-control @error('symbols') is-invalid @enderror" value="{{ old('symbols', implode(', ', $featured)) }}" maxlength="120" placeholder="FPT, VNM, ACB">
            @error('symbols')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-hint">Chỉ chọn được mã có trong danh sách cổ phiếu của hệ thống. Thẻ trên trang chủ cập nhật ngay sau khi lưu.</div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Lưu</button></div>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-eraser me-2 text-primary"></i>Xóa cache</h3></div>
    <div class="list-group list-group-flush">
        @foreach($cacheGroups as $group => [$label, $desc])
            <div class="list-group-item d-flex align-items-center gap-3">
                <div class="flex-fill"><div class="fw-semibold">{{ $label }}</div><div class="text-secondary small">{{ $desc }}</div></div>
                <form method="POST" action="{{ route('admin.site.cache', $group) }}">@csrf <button class="btn btn-outline-secondary"><i class="ti ti-trash me-1"></i> Xóa</button></form>
            </div>
        @endforeach
    </div>
    <div class="card-footer text-secondary small">Chỉ xóa phần được liệt kê; dữ liệu đồng bộ (giá, thị trường thế giới, tín hiệu…) nằm ở trang Sync Status.</div>
</div>
@endsection
