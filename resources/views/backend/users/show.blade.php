@extends('layouts.admin')

@section('title', 'Chi tiết User')
@section('page_pretitle', 'Hệ thống')
@section('page_title', $user->name)

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.users.index') }}">Users</a>
    </li>
    <li class="breadcrumb-item active">{{ $user->name }}</li>
@endsection

@section('page_actions')
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/>
        </svg>
        Chỉnh sửa
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4">
            <!-- User Info Card -->
            <div class="card">
                <div class="card-body text-center">
                    <span class="avatar avatar-xl mb-3">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    <h3 class="m-0 mb-1">{{ $user->name }}</h3>
                    <div class="text-muted">{{ $user->email }}</div>
                    <div class="mt-3">
                        @foreach($user->roles as $role)
                            <span class="badge badge-outline text-blue me-1">{{ $role->display_name }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="d-flex">
                    <a href="{{ route('admin.users.edit', $user) }}" class="card-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/>
                        </svg>
                        Chỉnh sửa
                    </a>
                </div>
            </div>

            <!-- Account Details -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Chi tiết tài khoản</h3>
                </div>
                <div class="list-group list-group-flush">
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="status-dot {{ $user->email_verified_at ? 'd-block' : 'status-dot-animated bg-red' }}"></span>
                            </div>
                            <div class="col text-truncate">
                                <strong>Trạng thái</strong>
                                <div class="d-block text-muted text-truncate">
                                    {{ $user->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            </div>
                            <div class="col text-truncate">
                                <strong>Ngày tạo</strong>
                                <div class="d-block text-muted text-truncate">
                                    {{ $user->created_at->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @if($user->profile && $user->profile->mobile)
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/>
                                </svg>
                            </div>
                            <div class="col text-truncate">
                                <strong>Số điện thoại</strong>
                                <div class="d-block text-muted text-truncate">
                                    {{ $user->profile->mobile }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- User Portfolios -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Portfolios ({{ $user->portfolios->count() }})</h3>
                </div>
                <div class="card-body">
                    @if($user->portfolios->count() > 0)
                        <div class="divide-y">
                            @foreach($user->portfolios as $portfolio)
                            <div class="row">
                                <div class="col-auto">
                                    <span class="avatar bg-blue text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <rect x="3" y="4" width="18" height="12" rx="1"/>
                                            <line x1="7" y1="20" x2="17" y2="20"/>
                                            <line x1="9" y1="16" x2="15" y2="16"/>
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="text-truncate">
                                        <strong>{{ $portfolio->name }}</strong>
                                    </div>
                                    <div class="text-muted">{{ $portfolio->description }}</div>
                                </div>
                                <div class="col-auto align-self-center">
                                    @if($portfolio->is_active)
                                        <span class="badge bg-success">Hoạt động</span>
                                    @else
                                        <span class="badge bg-secondary">Tạm dừng</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty">
                            <div class="empty-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="48" height="48" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <rect x="3" y="4" width="18" height="12" rx="1"/>
                                    <line x1="7" y1="20" x2="17" y2="20"/>
                                    <line x1="9" y1="16" x2="15" y2="16"/>
                                </svg>
                            </div>
                            <p class="empty-title">Chưa có portfolios nào</p>
                            <p class="empty-subtitle text-muted">User này chưa tạo portfolio nào.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection