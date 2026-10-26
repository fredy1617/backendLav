<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    private function cajaAbierta()
    {
        return CashRegister::whereDate('date', today())
            ->whereNull('closed_at')
            ->first();
    }
    
    private function calcularEfectivo(CashRegister $caja): float
    {
        $metodosEfectivo = ['Efectivo', 'efectivo', 'cash', 'Cash'];

        // Fondo inicial de caja
        $fondoInicial = (float) $caja->opening_amount;

        // Pagos recibidos en efectivo
        $pagosEfectivo = (float) $caja->payments()
            ->whereIn('payment_method', $metodosEfectivo)
            ->sum('amount');

        // Ingresos manuales en efectivo
        $ingresosEfectivo = (float) $caja->movements()
            ->where('type', 'ingreso')
            ->whereIn('method', $metodosEfectivo)
            ->sum('amount');

        // Retiros manuales en efectivo
        $retirosEfectivo = (float) $caja->movements()
            ->where('type', 'retiro')
            ->whereIn('method', $metodosEfectivo)
            ->sum('amount');

        return round(
            $fondoInicial + $pagosEfectivo + $ingresosEfectivo - $retirosEfectivo, 2
        );
    }

    
    public function current()
    {
        $caja = $this->cajaAbierta();

        if (!$caja) {
            return response()->json(['data' => null, 'message' => 'No hay una caja abierta para hoy.',]);
        }

        $caja->load([
            'movements' => fn ($q) => $q->latest(),
            'payments' => fn ($q) => $q
                ->with('order.customer')
                ->latest(),
        ]);

        $caja->cash_payments_total = (float) $caja->payments()
            ->whereIn('payment_method', [
                'Efectivo',
                'efectivo',
                'cash',
                'Cash',
            ])
            ->sum('amount');

        $caja->payments_total = (float) $caja->payments()->sum('amount');

        $caja->expected_amount = $this->calcularEfectivo($caja);

        return response()->json(['data' => $caja,]);
    }


    public function history(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = CashRegister::query()
            ->when($request->filled('date_from'), fn ($q) =>
                $q->whereDate('date', '>=', $request->date_from)
            )
            ->when($request->filled('date_to'), fn ($q) =>
                $q->whereDate('date', '<=', $request->date_to)
            )
            ->orderByDesc('date');

        return response()->json($query->paginate(15));
    }

    public function open(Request $request)
    {
        $validated = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'opening_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $caja = DB::transaction(function () use ($validated) {
            // Evita abrir otra caja para el mismo día.
            $existente = CashRegister::whereDate('date', today())
                ->lockForUpdate()
                ->first();

            if ($existente) {
                abort(422, $existente->closed_at
                    ? 'La caja de hoy ya fue cerrada. No se puede abrir otra caja para la misma fecha.'
                    : 'Ya existe una caja abierta para hoy.');
            }

            return CashRegister::create([
                'date' => today()->toDateString(),
                'opening_amount' => round((float) $validated['opening_amount'], 2),
                'expected_amount' => round((float) $validated['opening_amount'], 2),
                'opened_at' => now(),
                'opening_notes' => $validated['opening_notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Caja abierta correctamente.', 'data' => $caja,
        ], 201);
    }

    public function movement(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:ingreso,retiro'],
            'concept' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'method' => ['required', 'in:Efectivo,Transferencia,Tarjeta'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $movimiento = DB::transaction(function () use ($validated) {
            $caja = CashRegister::whereDate('date', today())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (!$caja) {
                abort(422, 'Debes abrir la caja antes de registrar movimientos.');
            }

            if (
                $validated['type'] === 'retiro'
                && $validated['method'] === 'Efectivo'
            ) {
                $efectivoActual = $this->calcularEfectivo($caja);

                if ((float) $validated['amount'] > $efectivoActual) {
                    abort(422, 'El retiro supera el efectivo disponible en caja.');
                }
            }

            $movimiento = $caja->movements()->create([
                'type' => $validated['type'],
                'concept' => $validated['concept'],
                'amount' => round((float) $validated['amount'], 2),
                'method' => $validated['method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $caja->expected_amount = $this->calcularEfectivo($caja);
            $caja->save();

            return $movimiento;
        });

        return response()->json([
            'message' => 'Movimiento registrado correctamente.', 'data' => $movimiento,
        ], 201);
    }

    public function close(Request $request)
    {
        $validated = $request->validate([
            'counted_amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $caja = DB::transaction(function () use ($validated) {
            $caja = CashRegister::whereDate('date', today())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (!$caja) {
                abort(422, 'No existe una caja abierta para cerrar.');
            }

            $esperado = $this->calcularEfectivo($caja);
            $contado = round((float) $validated['counted_amount'], 2);

            $caja->expected_amount = $esperado;
            $caja->counted_amount = $contado;
            $caja->difference = round($contado - $esperado, 2);
            $caja->closing_notes = $validated['closing_notes'] ?? null;
            $caja->closed_at = now();
            $caja->save();

            return $caja;
        });

        return response()->json([
            'message' => 'Caja cerrada correctamente.', 'data' => $caja,
        ]);
    }
}
