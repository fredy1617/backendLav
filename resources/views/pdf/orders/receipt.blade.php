
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .line { border-top: 1px dashed #888; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 5px 2px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        .total { font-size: 12px; font-weight: bold; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <div class="center">
        <h2 style="margin: 0;">LAVANDERÍA</h2>
        <p style="margin: 4px 0;">TICKET DE RECEPCIÓN</p>
        <h3 style="margin: 4px 0;">ORDEN #{{ $order->id }}</h3>
    </div>

    <div class="line"></div>

    <p><strong>Cliente:</strong> {{ $order->customer->name ?? 'Sin nombre' }}</p>
    <p><strong>Teléfono:</strong> {{ $order->customer->phone ?? '—' }}</p>
    <p><strong>Recepción:</strong>
        {{ $order->received_at
            ? \Carbon\Carbon::parse($order->received_at)->format('d/m/Y')
            : '—' }}
    </p>
    <p><strong>Entrega estimada:</strong>
        {{ $order->estimated_delivery_at
            ? \Carbon\Carbon::parse($order->estimated_delivery_at)->format('d/m/Y')
            : 'Por confirmar' }}
    </p>
    <p><strong>Estado:</strong> {{ $order->status }}</p>

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th>Prenda / servicio</th>
                <th class="right">Cant.</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->details as $detail)
                <tr>
                    <td>{{ $detail->service_name }}</td>
                    <td class="right">{{ $detail->quantity }}</td>
                    <td class="right">
                        ${{ number_format((float) $detail->subtotal, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Sin detalles registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td>Total de la orden</td>
            <td class="right total">
                ${{ number_format((float) $order->total, 2) }}
            </td>
        </tr>
        <tr>
            <td>Total abonado</td>
            <td class="right">
                ${{ number_format($totalPagado, 2) }}
            </td>
        </tr>
        <tr>
            <td>Saldo pendiente</td>
            <td class="right">
                ${{ number_format($saldo, 2) }}
            </td>
        </tr>
    </table>

    @if($order->observations)
        <div class="line"></div>
        <p><strong>Observaciones:</strong> {{ $order->observations }}</p>
    @endif

    <div class="line"></div>

    <p class="center">Revise sus prendas al recibirlas.</p>
    <p class="center muted">Conserve este ticket para recoger su pedido.</p>
    <p class="center">Gracias por su preferencia.</p>
</body>
</html>

