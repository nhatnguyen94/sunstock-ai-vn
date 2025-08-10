@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4 text-primary">Tỷ giá ngoại tệ Vietcombank 3 ngày gần nhất</h2>
    @foreach($rates as $date => $items)
        <h5 class="mt-4 text-info">{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="thead-light">
                    <tr>
                        <th>Mã</th>
                        <th>Tên</th>
                        <th>Đơn vị tiền tệ</th>
                        <th>Mua tiền mặt</th>
                        <th>Mua chuyển khoản</th>
                        <th>Bán</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td>{{ $item['currency_code'] }}</td>
                        <td>{{ $item['currency_name'] }}</td>
                        <td>1 {{ $item['currency_code'] }} = VNĐ</td>
                        <td>{{ $item['buy_cash'] }}</td>
                        <td>{{ $item['buy_transfer'] }}</td>
                        <td>{{ $item['sell'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection