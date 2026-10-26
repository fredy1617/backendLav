<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:150'],
            'method' => ['nullable', 'string', 'max:30'],
        ]);

        $query = Payment::with(['order.customer'])
            ->when($request->filled('date_from'), fn ($q) =>
                $q->whereDate('created_at', '>=', $validated['date_from'])
            )
            ->when($request->filled('date_to'), fn ($q) =>
                $q->whereDate('created_at', '<=', $validated['date_to'])
            )
            ->when($request->filled('method'), fn ($q) =>
                $q->where('method', $validated['method'])
            )
            ->when($request->filled('search'), function ($q) use ($validated) {
                $search = trim($validated['search']);

                $q->where(function ($sub) use ($search) {
                    $sub->where('id', 'like', "%{$search}%")
                        ->orWhere('order_id', 'like', "%{$search}%")
                        ->orWhereHas('order.customer', function ($customer) use ($search) {
                            $customer->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at');

        $payments = $query->paginate(20);

        return response()->json($payments);
    }

    public function summary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Payment::query()
            ->when($request->filled('date_from'), fn ($q) =>
                $q->whereDate('created_at', '>=', $validated['date_from'])
            )
            ->when($request->filled('date_to'), fn ($q) =>
                $q->whereDate('created_at', '<=', $validated['date_to'])
            );

        $pagos = (clone $query)->get();

        $porMetodo = $pagos->groupBy(
            fn ($p) => $p->method ?: 'Sin especificar'
        )->map(fn ($grupo) => [
            'cantidad' => $grupo->count(),
            'total' => round((float) $grupo->sum('amount'), 2),
        ]);

        return response()->json([
            'cantidad_pagos' => $pagos->count(),
            'total_cobrado' => round((float) $pagos->sum('amount'), 2),
            'por_metodo' => $porMetodo,
        ]);
    }
}
