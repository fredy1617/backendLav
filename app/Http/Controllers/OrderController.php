<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    private function saveDetails(Order $order, array $details): float
    {
        $total = 0;

        foreach ($details as $item) {
            $service = Service::findOrFail($item['service_id']);

            $quantity = (float) $item['quantity'];
            $unitPrice = round((float) $item['unit_price'], 2);

            // El servidor calcula el subtotal; no confía en el navegador.
            $subtotal = round($quantity * $unitPrice, 2);

            $order->details()->create([
                'service_id' => $service->id,
                'service_name' => $service->name,
                'pricing_type' => $service->pricing_type,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);

            $total = round($total + $subtotal, 2);
        }

        return $total;
    }

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
                'total' => 0,
            ]);

            $total = $this->saveDetails(
                $order,
                $validated['details']
            );

            $order->update(['total' => $total]);

            return $order;
        });

        return response()->json([
            'message' => 'Orden creada correctamente.',
            'data' => $order->load(['customer', 'details']),
        ], 201);
    }

    public function show(string $id)
    {
        $order = Order::with(['customer', 'details.service'])
            ->findOrFail($id);

        return response()->json([
            'data' => $order,
        ]);
    }

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

            // Sustituye los detalles por los enviados desde el formulario.
            $order->details()->delete();

            $total = $this->saveDetails(
                $order,
                $validated['details']
            );

            $order->update(['total' => $total]);

            return $order;
        });

        return response()->json([
            'message' => 'Orden actualizada correctamente.',
            'data' => $order->load(['customer', 'details']),
        ]);
    }

    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);

        $order->delete();

        return response()->json([
            'message' => 'Orden eliminada correctamente.',
        ]);
    }

    
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

            return $order->fresh(['customer', 'details', 'payments']);
        });

        return response()->json([
            'message' => 'Orden entregada correctamente.',
            'data' => $order,
        ]);
    }
    
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

        // Solo permitir Recibida -> En proceso.
        if (
            $estadoActual === 'Recibida' &&
            $nuevoEstado === 'En proceso'
        ) {
            $order->status = 'En proceso';
        }
        // Solo permitir En proceso -> Lista.
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

    public function ticketEntrega($id)
    {
        $order = Order::with(['customer', 'details', 'payments'])
            ->findOrFail($id);

        // Solo generar el ticket si la orden ya fue entregada.
        if ($order->status !== 'Entregada') {
            return response()->json(['message' => 'El ticket de entrega solo está disponible para órdenes entregadas.',], 422);
        }

        $total = (float) $order->total;

        $totalPagado = (float) $order->payments->sum('amount');

        $saldo = max(0, round($total - $totalPagado, 2));

        // Usa aquí el nombre de tu Blade de ticket de entrega.
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
