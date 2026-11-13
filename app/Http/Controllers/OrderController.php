<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private array $statuses = [
        'Recibida',
        'En proceso',
        'Lista',
        'Entregada',
    ];

    private function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],

            'received_at' => [
                'required',
                'date',
            ],

            'estimated_delivery_at' => [
                'nullable',
                'date',
                'after_or_equal:received_at',
            ],

            'status' => [
                'required',
                Rule::in($this->statuses),
            ],

            'observations' => [
                'nullable',
                'string',
                'max:5000',
            ],

            // Descuento general de toda la orden.
            'discount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999',
            ],

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],

            'details.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999',
            ],

            'details.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999',
            ],
        ];
    }

    /**
     * Guarda los detalles y devuelve el subtotal de los servicios.
     */
    private function saveDetails(Order $order, array $details): float
    {
        $subtotalGeneral = 0;

        foreach ($details as $item) {
            $service = Service::findOrFail($item['service_id']);

            $quantity = (float) $item['quantity'];
            $unitPrice = round((float) $item['unit_price'], 2);

            // El servidor calcula el subtotal.
            $subtotal = round($quantity * $unitPrice, 2);

            $order->details()->create([
                'service_id' => $service->id,
                'service_name' => $service->name,
                'pricing_type' => $service->pricing_type,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);

            $subtotalGeneral = round(
                $subtotalGeneral + $subtotal,
                2
            );
        }

        return $subtotalGeneral;
    }

    /**
     * Calcula subtotal, descuento y total.
     */
    private function actualizarTotales(
        Order $order,
        float $subtotal,
        float $discount
    ): void {
        $subtotal = round($subtotal, 2);
        $discount = round($discount, 2);

        if ($discount > $subtotal) {
            throw ValidationException::withMessages([
                'discount' => [
                    'El descuento no puede ser mayor al subtotal de la orden.',
                ],
            ]);
        }

        $order->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => round($subtotal - $discount, 2),
        ]);
    }

    /**
     * Listado de órdenes.
     */
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'details'])
            ->withSum('payments as total_paid', 'amount')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->search);

                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('received_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('received_at', '<=', $request->date_to);
            })
            ->orderByDesc('id');

        return response()->json($query->paginate(15));
    }

    /**
     * Crear orden.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $order = DB::transaction(function () use ($validated) {

            $order = Order::create([
                'customer_id' => $validated['customer_id'],
                'received_at' => $validated['received_at'],
                'estimated_delivery_at' =>
                    $validated['estimated_delivery_at'] ?? null,
                'status' => $validated['status'],
                'observations' => $validated['observations'] ?? null,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
            ]);

            $subtotal = $this->saveDetails(
                $order,
                $validated['details']
            );

            $discount = round(
                (float) ($validated['discount'] ?? 0),
                2
            );

            $this->actualizarTotales(
                $order,
                $subtotal,
                $discount
            );

            return $order;
        });

        return response()->json([
            'message' => 'Orden creada correctamente.',
            'data' => $order->load(['customer', 'details']),
        ], 201);
    }

    /**
     * Consultar orden.
     */
    public function show(string $id)
    {
        $order = Order::with(['customer', 'details.service'])
            ->findOrFail($id);

        return response()->json([
            'data' => $order,
        ]);
    }

    /**
     * Actualizar orden.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->rules());

        $order = DB::transaction(function () use ($validated, $id) {

            $order = Order::findOrFail($id);

            $order->update([
                'customer_id' => $validated['customer_id'],
                'received_at' => $validated['received_at'],
                'estimated_delivery_at' =>
                    $validated['estimated_delivery_at'] ?? null,
                'status' => $validated['status'],
                'observations' => $validated['observations'] ?? null,
            ]);

            // Sustituir los detalles anteriores.
            $order->details()->delete();

            $subtotal = $this->saveDetails(
                $order,
                $validated['details']
            );

            $discount = round(
                (float) ($validated['discount'] ?? 0),
                2
            );

            $this->actualizarTotales(
                $order,
                $subtotal,
                $discount
            );

            return $order;
        });

        return response()->json([
            'message' => 'Orden actualizada correctamente.',
            'data' => $order->load(['customer', 'details']),
        ]);
    }

    /**
     * Eliminar orden.
     */
    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);

        $order->delete();

        return response()->json([
            'message' => 'Orden eliminada correctamente.',
        ]);
    }

    /**
     * Consultar recomendación de descuento por lealtad.
     *
     * Se toman las últimas cinco órdenes entregadas del cliente.
     * No se aplica ningún descuento automáticamente.
     */
    public function customerLoyalty(Request $request, string $customerId)
    {
        $request->validate([
            'exclude_order_id' => [
                'nullable',
                'integer',
                'exists:orders,id',
            ],
        ]);

        $query = Order::where('customer_id', $customerId)
            ->where('status', 'Entregada');

        // Al editar una orden, no contarla como una orden anterior.
        if ($request->filled('exclude_order_id')) {
            $query->where(
                'id',
                '!=',
                $request->exclude_order_id
            );
        }

        $orders = $query
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'total']);

        $cantidad = $orders->count();

        $gastoAnterior = round(
            (float) $orders->sum('total'),
            2
        );

        $recomendacion = $cantidad === 5
            ? round($gastoAnterior * 0.10, 2)
            : 0;

        return response()->json([
            'eligible' => $cantidad === 5,
            'previous_orders' => $cantidad,
            'spending' => $gastoAnterior,
            'recommended_discount' => $recomendacion,
            'message' => $cantidad === 5
                ? 'Este cliente cuenta con cinco órdenes entregadas. Se recomienda un descuento del 10% sobre el gasto acumulado de esas órdenes.'
                : "Este cliente tiene {$cantidad} de 5 órdenes entregadas necesarias para obtener una recomendación.",
        ]);
    }

    /**
     * Ticket de recepción.
     */
    public function ticketRecepcion($id)
    {
        $order = Order::with(['customer', 'details'])
            ->findOrFail($id);

        $pdf = Pdf::loadView(
            'pdf.ticket-recepcion',
            compact('order')
        )->setPaper([0, 0, 226.77, 566.93]);

        return $pdf->stream(
            'ticket-recepcion-' . $order->id . '.pdf'
        );
    }

    /**
     * Entregar orden.
     */
    public function deliver($id)
    {
        $order = DB::transaction(function () use ($id) {

            $order = Order::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'Lista') {
                abort(422, 'Solo se pueden entregar órdenes que estén listas.');
            }

            $totalPagado = (float) $order->payments()->sum('amount');
            $totalOrden = (float) $order->total;

            $saldo = round($totalOrden - $totalPagado, 2);

            if ($saldo > 0.009) {
                abort(
                    422,
                    'No se puede entregar la orden. Saldo pendiente: $' .
                    number_format($saldo, 2, '.', ',')
                );
            }

            $order->status = 'Entregada';
            $order->save();

            return $order->fresh([
                'customer',
                'details',
                'payments',
            ]);
        });

        return response()->json([
            'message' => 'Orden entregada correctamente.',
            'data' => $order,
        ]);
    }

    /**
     * Ticket de pagos / recepción.
     */
    public function receiptTicket($id)
    {
        $order = Order::with(['customer', 'details', 'payments'])
            ->findOrFail($id);

        $totalPagado = (float) $order->payments()->sum('amount');

        $saldo = max(
            0,
            round((float) $order->total - $totalPagado, 2)
        );

        $pdf = Pdf::loadView('pdf.orders.receipt', [
            'order' => $order,
            'totalPagado' => $totalPagado,
            'saldo' => $saldo,
        ])->setPaper([0, 0, 226.77, 600], 'portrait');

        return $pdf->stream('orden-' . $order->id . '-recepcion.pdf');
    }

    /**
     * Cambiar estado.
     */
    public function updateStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['En proceso', 'Lista']),
            ],
        ]);

        $order = Order::findOrFail($id);

        $estadoActual = $order->status;
        $nuevoEstado = $validated['status'];

        // Recibida -> En proceso.
        if (
            $estadoActual === 'Recibida' &&
            $nuevoEstado === 'En proceso'
        ) {
            $order->status = 'En proceso';
        }

        // En proceso -> Lista.
        elseif (
            $estadoActual === 'En proceso' &&
            $nuevoEstado === 'Lista'
        ) {
            $order->status = 'Lista';
        } else {
            return response()->json([
                'message' => 'El cambio de estado solicitado no está permitido.',
            ], 422);
        }

        $order->save();

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $order->load(['customer', 'details']),
        ]);
    }

    /**
     * Ticket de entrega.
     */
    public function ticketEntrega($id)
    {
        $order = Order::with(['customer', 'details', 'payments'])
            ->findOrFail($id);

        if ($order->status !== 'Entregada') {
            return response()->json([
                'message' => 'El ticket de entrega solo está disponible para órdenes entregadas.',
            ], 422);
        }

        $total = (float) $order->total;

        $totalPagado = (float) $order->payments->sum('amount');

        $saldo = max(
            0,
            round($total - $totalPagado, 2)
        );

        $pdf = Pdf::loadView('pdf.orders.ticket-entrega', [
            'order' => $order,
            'total' => $total,
            'totalPagado' => $totalPagado,
            'saldo' => $saldo,
        ])->setPaper([0, 0, 226.77, 750], 'portrait');

        return $pdf->stream(
            'ticket-entrega-' . $order->id . '.pdf'
        );
    }
}
