<?php

namespace App\Backend\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        return view('backend.dashboard');
    }
}