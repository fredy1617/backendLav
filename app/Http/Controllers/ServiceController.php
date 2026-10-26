<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active') && $request->active !== 'todos') {
            $query->where('active', $request->active);
        }

        return response()->json(
            $query->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'pricing_type' => [
                'required',
                Rule::in(['prenda', 'kilogramo', 'servicio']),
            ],
            'price' => 'required|numeric|min:0|max:99999999.99',
            'active' => 'sometimes|boolean',
        ]);

        $service = Service::create($validated);

        return response()->json([
            'message' => 'Servicio registrado correctamente.',
            'data' => $service,
        ], 201);
    }

    public function show(string $id)
    {
        return response()->json(
            Service::findOrFail($id)
        );
    }

    public function update(Request $request, string $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'pricing_type' => [
                'required',
                Rule::in(['prenda', 'kilogramo', 'servicio']),
            ],
            'price' => 'required|numeric|min:0|max:99999999.99',
            'active' => 'required|boolean',
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Servicio actualizado correctamente.',
            'data' => $service->fresh(),
        ]);
    }

    public function destroy(string $id)
    {
        $service = Service::findOrFail($id);

        $service->delete();

        return response()->json([
            'message' => 'Servicio eliminado correctamente.',
        ]);
    }

}