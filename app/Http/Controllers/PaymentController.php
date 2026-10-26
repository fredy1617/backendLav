<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with('order.customer')
            ->orderByDesc('paid_at');

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }

        return response()->json($query->paginate(20));
    }

    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => [
                'required',
                Rule::in(['Efectivo', 'Tarjeta', 'Transferencia', 'otro']),
            ],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment = DB::transaction(function () use ($validated) {
            /*------------------------------------------------------------------------
            | 1. Buscar y bloquear la caja abierta del día
            |--------------------------------------------------------------------------*/
            $caja = CashRegister::whereDate('date', today())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (!$caja) {
                abort(422, 'No hay una caja abierta para hoy. Abre la caja antes de registrar pagos.');
            }

            /*-----------------------------------------------------------------------
            | 2. Buscar y bloquear la orden
            |-------------------------------------------------------------------------*/
            $order = Order::whereKey($validated['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === 'Entregada') {
                abort(422, 'No se pueden registrar pagos en una orden entregada.');
            }

            /*-------------------------------------------------------------------------
            | 3. Calcular el saldo pendiente
            |--------------------------------------------------------------------------*/
            $totalPagado = (float) $order->payments()->sum('amount');
            $totalOrden = (float) $order->total;
            $saldo = max(0, round($totalOrden - $totalPagado, 2));
            $importe = round((float) $validated['amount'], 2);

            if ($saldo <= 0) {
                abort(422, 'Esta orden ya está liquidada.');
            }

            if ($importe > $saldo) {
                abort(422, 'El pago supera el saldo pendiente de $' . number_format($saldo, 2, '.', ',') . '.');
            }

            /*------------------------------------------------------------------------
            | 4. Registrar el pago asociado a la caja
            |-------------------------------------------------------------------------*/
            $payment = $order->payments()->create([
                'cash_register_id' => $caja->id,
                'amount' => $importe,
                'payment_method' => $validated['payment_method'],
                'paid_at' => $validated['paid_at'] ?? now(),
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            /*------------------------------------------------------------------------
            | 5. Generar folio del recibo
            |-------------------------------------------------------------------------*/
            $payment->update([
                'receipt_folio' => 'PAG-' . now()->format('Y') . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $payment->fresh('order.customer');
        });

        /*------------------------------------------------------------------------
        | 6. Responder con el total pagado y el saldo actualizado
        |------------------------------------------------------------------------*/
        $totalPagadoActual = (float) $payment->order
            ->payments()
            ->sum('amount');

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'data' => $payment,
            'total_paid' => $totalPagadoActual,
            'balance' => max(0, round((float) $payment->order->total - $totalPagadoActual, 2)),
        ], 201);
    }

    public function ticket(Payment $payment)
    {
        $payment->load('order.customer', 'order.details', 'order.payments');

        $totalPagado = (float) $payment->order->payments()->sum('amount');
        $saldo = max(0, round((float) $payment->order->total - $totalPagado, 2));

        $pdf = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'order' => $payment->order,
            'totalPagado' => $totalPagado,
            'saldo' => $saldo,
        ])->setPaper([0, 0, 226.77, 500], 'portrait');

        return $pdf->stream(
            ($payment->receipt_folio ?? 'pago-' . $payment->id) . '.pdf'
        );
    }
}
