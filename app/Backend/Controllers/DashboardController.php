<?php

namespace App\Backend\Controllers;

use App\Models\User;
use App\Models\Portfolio;
use App\Models\Stock;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Hiển thị trang dashboard admin
     */
    public function index()
    {
        // Thống kê tổng quan
        $stats = [
            'total_users' => User::count(),
            'total_portfolios' => Portfolio::count(),
            'total_stocks' => Stock::count(),
            'active_portfolios' => Portfolio::where('is_active', true)->count(),
        ];

        // Người dùng mới nhất
        $recent_users = User::with('roles')
            ->latest()
            ->take(5)
            ->get();

        // Portfolio hoạt động mới nhất
        $recent_portfolios = Portfolio::with('user')
            ->where('is_active', true)
            ->latest()
            ->take(5)
            ->get();

        return view('backend.dashboard.index', compact(
            'stats', 
            'recent_users', 
            'recent_portfolios'
        ));
    }
}
