<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function customer($id)
    {
        $customer = Customer::findOrFail($id);

        // Últimas 5 órdenes entregadas del cliente.
        $orders = Order::where('customer_id', $customer->id)
            ->where('status', 'Entregada')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'total']);

        $visitas = $orders->count();

        $totalGastado = round(
            (float) $orders->sum('total'),
            2
        );

        $porcentaje = 10;

        $descuentoRecomendado = round(
            $totalGastado * ($porcentaje / 100),
            2
        );

        return response()->json([
            'customer_id' => $customer->id,
            'visitas' => $visitas,
            'total_gastado' => $totalGastado,
            'porcentaje' => $porcentaje,
            'descuento_recomendado' => $descuentoRecomendado,
            'elegible' => $visitas >= 5,
        ]);
    }
}