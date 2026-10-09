<?php

namespace App\Http\Middleware;

use App\Support\TransformerResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAccess
{
    /**
     * Handle an incoming request.
     * Chặn tất cả user không có quyền truy cập backend
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Kiểm tra user đã đăng nhập chưa - redirect đến admin login
        if (! Auth::check()) {
            return TransformerResponse::redirectError('admin.login', 'Bạn cần đăng nhập quản trị để truy cập trang này.');
        }

        // Kiểm tra user có quyền truy cập backend không
        if (! Auth::user()->canAccessBackend()) {
            return TransformerResponse::redirectError('home', 'Bạn không có quyền truy cập vào khu vực quản trị.');
        }

        return $next($request);
    }
}
