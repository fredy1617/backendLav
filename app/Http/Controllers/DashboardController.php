<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Estadísticas y vista rápida del Dashboard.
     */
    public function index()
    {
        try {
            $segments = [
                'upcoming_discounts' => 'upcoming',
                'frequent_customers' => 'frequent',
                'customers_to_recover' => 'recover',
            ];

            $data = [];

            foreach ($segments as $key => $segment) {
                $query = $this->customersQuery($segment);

                $data[$key] = [
                    'count' => (clone $query)->count(),
                    'customers' => (clone $query)
                        ->limit(5)
                        ->get()
                        ->map(fn ($customer) => $this->formatCustomer($customer)),
                ];
            }

            $data['recent_activity'] = Order::with('customer')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'folio' => 'ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                    'customer' => $order->customer?->name ?? 'Cliente eliminado',
                    'total' => (float) $order->total,
                    'status' => $order->status,
                    'received_at' => $order->received_at,
                ]);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Lista de hasta 30 clientes de una categoría.
     */
    public function customers(Request $request, string $segment)
    {
        try {
            if (!in_array($segment, ['upcoming', 'frequent', 'recover'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría de clientes no válida.',
                ], 422);
            }

            $search = trim((string) $request->query('search', ''));

            $query = $this->customersQuery($segment);

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('c.name', 'like', "%{$search}%")
                        ->orWhere('c.phone', 'like', "%{$search}%");
                });
            }

            $customers = $query
                ->orderByDesc('stats.services_count')
                ->limit(30)
                ->get()
                ->map(fn ($customer) => $this->formatCustomer($customer));

            return response()->json([
                'success' => true,
                'data' => $customers,
                'count' => $customers->count(),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Información completa e historial de órdenes de un cliente.
     */    
    public function customerDetail($customerId)
    {
        try {
            $customer = DB::table('customers')
                ->where('id', $customerId)
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado.',
                ], 404);
            }

            $orders = Order::with('details')
                ->where('customer_id', $customerId)
                ->orderByDesc('id')
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'folio' => 'ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                        'received_at' => $order->received_at,
                        'estimated_delivery_at' => $order->estimated_delivery_at,
                        'status' => $order->status,
                        'observations' => $order->observations,
                        'subtotal' => (float) ($order->subtotal ?? 0),
                        'discount' => (float) ($order->discount ?? 0),
                        'total' => (float) $order->total,
                        'details' => $order->details->map(fn ($detail) => [
                            'id' => $detail->id,
                            'service_name' => $detail->service_name,
                            'quantity' => (float) $detail->quantity,
                            'unit_price' => (float) $detail->unit_price,
                            'subtotal' => (float) $detail->subtotal,
                        ])->values(),
                    ];
                })
                ->values();

            // Filtrar órdenes entregadas
            $delivered = $orders->where('status', 'Entregada');

            // Última visita del cliente
            $lastVisit = $delivered->first()['received_at'] ?? null;

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone ?? null,
                    ],
                    'summary' => [
                        'total_orders' => $orders->count(),
                        'delivered_orders' => $delivered->count(),
                        'total_spent' => round(
                            (float) $delivered->sum('total'),
                            2
                        ),
                        'last_visit' => $lastVisit,
                    ],
                    'orders' => $orders,
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }


    /**
     * Consulta común para clasificar clientes.
     *
     * Solo considera órdenes entregadas durante los últimos 90 días.
     */
    private function customersQuery(string $segment)
    {
        $from90Days = now()->subDays(90)->toDateString();
        $recoverDate = now()->subDays(15)->toDateString();

        $stats = DB::table('orders')
            ->select('customer_id')
            ->selectRaw('COUNT(*) as services_count')
            ->selectRaw('SUM(total) as total_spent')
            ->selectRaw('MAX(received_at) as last_visit')
            ->where('status', 'Entregada')
            ->whereDate('received_at', '>=', $from90Days)
            ->groupBy('customer_id');

        $query = DB::table('customers as c')
            ->joinSub($stats, 'stats', function ($join) {
                $join->on('stats.customer_id', '=', 'c.id');
            })
            ->select(
                'c.id',
                'c.name',
                'c.phone',
                'stats.services_count',
                'stats.total_spent',
                'stats.last_visit'
            );

        if ($segment === 'upcoming') {
            // Cuatro órdenes entregadas: falta una para completar cinco.
            $query->where('stats.services_count', '=', 4);
        }

        if ($segment === 'frequent') {
            $query->where('stats.services_count', '>=', 3);
        }

        if ($segment === 'recover') {
            $query->where('stats.services_count', '>=', 4)
                ->whereDate('stats.last_visit', '<=', $recoverDate);
        }

        if ($segment === 'recover') {
            $query->orderBy('stats.last_visit');
        } else {
            $query->orderByDesc('stats.services_count')
                ->orderByDesc('stats.last_visit');
        }

        return $query;
    }

    private function formatCustomer($customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'services_count' => (int) $customer->services_count,
            'total_spent' => (float) $customer->total_spent,
            'last_visit' => $customer->last_visit,
            'days_without_visit' => $customer->last_visit
                ? (int) \Carbon\Carbon::parse($customer->last_visit)->diffInDays(now())
                : null,
        ];
    }

    private function errorResponse(\Throwable $e)
    {
        \Log::error('Error en Dashboard: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'No fue posible cargar la información del Dashboard.',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}
