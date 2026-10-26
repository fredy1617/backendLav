<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
        }
        .center { text-align: center; }
        .muted { color: #666; }
        .line { border-top: 1px dashed #888; margin: 9px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 5px 2px; text-align: left; }
        .right { text-align: right; }
        .total { font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="center">
        <h2 style="margin: 0;">LAVANDERÍA</h2>
        <p style="margin: 4px 0;">COMPROBANTE DE PAGO</p>
        <p class="muted" style="margin: 0;">
            Folio: {{ $payment->receipt_folio ?? ('PAG-' . $payment->id) }}
        </p>
    </div>

    <div class="line"></div>

    <p><strong>Orden:</strong> #{{ $order->id }}</p>
    <p><strong>Cliente:</strong> {{ $order->customer->name ?? 'Sin nombre' }}</p>
    <p><strong>Fecha de pago:</strong>
        {{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}
    </p>
    <p><strong>Método:</strong> {{ ucfirst($payment->payment_method) }}</p>

    @if($payment->reference)
        <p><strong>Referencia:</strong> {{ $payment->reference }}</p>
    @endif

    <div class="line"></div>

    <table>
        <tr>
            <td>Total de la orden</td>
            <td class="right">
                ${{ number_format((float) $order->total, 2) }}
            </td>
        </tr>
        <tr>
            <td>Este pago</td>
            <td class="right total">
                ${{ number_format((float) $payment->amount, 2) }}
            </td>
        </tr>
        <tr>
            <td>Total abonado</td>
            <td class="right">
                ${{ number_format($totalPagado, 2) }}
            </td>
        </tr>
        <tr>
            <td><strong>Saldo pendiente</strong></td>
            <td class="right total">
                ${{ number_format($saldo, 2) }}
            </td>
        </tr>
    </table>

    @if($payment->notes)
        <div class="line"></div>
        <p><strong>Observaciones:</strong> {{ $payment->notes }}</p>
    @endif

    <div class="line"></div>

    <p class="center">Conserve este comprobante.</p>
    <p class="center muted">Gracias por su preferencia.</p>
</body>
</html>
