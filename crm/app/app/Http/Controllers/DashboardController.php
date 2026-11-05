<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\HomeData;
use Illuminate\Http\Request;

/** The role-aware Home (UX-FLOWS §2). All numbers come from HomeData, guarded per module. */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $home = new HomeData($request->user());
        $data = $home->toArray();
        $view = match ($data['role']) {
            'owner', 'admin' => 'home.owner',
            'finance' => 'home.finance',
            'technician' => 'home.technician',
            default => 'home.sales',
        };

        return view($view, $data + ['home' => $home]);
    }
}
