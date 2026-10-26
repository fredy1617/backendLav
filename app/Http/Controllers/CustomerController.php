<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'observations' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'message' => 'Cliente creado correctamente.',
            'data' => $customer,
        ], 201);
    }

    public function show(string $id)
    {
        $customer = Customer::findOrFail($id);

        return response()->json($customer);
    }

    public function update(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'observations' => 'nullable|string',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Cliente actualizado correctamente.',
            'data' => $customer->fresh(),
        ]);
    }

    public function destroy(string $id)
    {
        $customer = Customer::findOrFail($id);

        $customer->delete();

        return response()->json([
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }
}
