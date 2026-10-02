<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $campusId = $user->campus_id;

        $query = EquipmentInventory::with(['equipment', 'campus', 'location'])
            ->where('campus_id', $campusId);

        if ($request->category) {
            $query->whereHas('equipment', function ($q) use ($request) {
                $q->where('category', $request->category);
            });
        }

        if ($request->search) {
            $query->whereHas('equipment', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        $inventory = $query->paginate(15);
        $categories = Equipment::distinct()->pluck('category')->filter();

        return Inertia::render('Lab/Equipment/Index', [
            'inventory' => $inventory,
            'categories' => $categories,
            'filters' => $request->only(['category', 'search']),
            'campus' => Campus::find($campusId),
        ]);
    }
}

