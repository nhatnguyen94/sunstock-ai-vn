@extends('backend.layouts.app')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <h3 class="mb-4">Đăng nhập Admin</h3>
            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label>Mật khẩu</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
            </form>
        </div>
    </div>
</div>
@endsection