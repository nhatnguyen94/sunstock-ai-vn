<?php

namespace App\Backend\Controllers;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('backend.dashboard');
    }
}