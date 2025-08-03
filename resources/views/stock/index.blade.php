@extends('layouts.app')

@section('content')
    <h2 class="mb-4">Tra cứu mã chứng khoán</h2>

    <form method="POST" action="/search">
        @csrf
        <div class="form-group">
            <label for="code">Nhập mã:</label>
            <input type="text" name="code" id="code" class="form-control" placeholder="VD: FPT, VNM, VCB">
        </div>
        <button type="submit" class="btn btn-primary mt-2">Tra cứu</button>
    </form>

    @isset($result)
        <div class="alert alert-success mt-4">
            <h4>Kết quả cho mã: {{ $code }}</h4>
            <p><strong>Tên công ty:</strong> {{ $result['name'] }}</p>
            <p><strong>Giá hiện tại:</strong> {{ number_format($result['price']) }} VND</p>
        </div>
    @elseif(isset($code))
        <div class="alert alert-danger mt-4">
            Không tìm thấy thông tin cho mã: {{ $code }}
        </div>
    @endisset
@endsection
