<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // Numbers are filled in as each module ships (sales, dues, stock, leads).
        return view('dashboard', ['user' => $request->user()]);
    }
}
