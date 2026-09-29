<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Location;
use App\Models\User;
use Inertia\Inertia;

class MasterfileController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::count(),
            'campuses' => Campus::count(),
            'locations' => Location::count(),
            'equipment' => Equipment::count(),
            'categories' => Category::count(),
        ];

        return Inertia::render('Admin/Masterfile/Index', [
            'stats' => $stats,
        ]);
    }
}
